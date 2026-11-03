<?php

namespace Ulams\PolicyLifecycle\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Ulams\Compliance\Enums\AssignmentSource;
use Ulams\Compliance\Services\AssignmentService;
use Ulams\Compliance\Services\CourseSettings;
use Ulams\Compliance\Services\NotificationLog;
use Ulams\Core\Models\User;
use Ulams\Courses\Models\Course;
use Ulams\PolicyLifecycle\Events\PolicyChangeApproved;
use Ulams\PolicyLifecycle\Events\PolicyRetrainingAssigned;
use Ulams\PolicyLifecycle\Models\Policy;
use Ulams\PolicyLifecycle\Models\PolicyCertificate;
use Ulams\PolicyLifecycle\Models\PolicyReassignment;
use Ulams\PolicyLifecycle\Models\PolicyRollout;
use Ulams\PolicyLifecycle\Models\PolicyVariant;
use Ulams\PolicyLifecycle\Models\PolicyVersion;
use Ulams\TemplatesPdf\Models\FabricPDF;
use Ulams\TemplatesPdf\Services\CertificateVerificationService;

/**
 * What an approved policy change does to people, after every department course has been updated (ADR 0140, plan 4.3
 * steps 8 to 10). For everyone whose live certificate certified the previous version of a department's course: the
 * certificate is revoked (reason `policy_superseded:v<N>`, N the new version), and they are given the exam course
 * (minor change) or the department course (major change) with the policy's due days. Learners who never completed keep
 * their open assignment and simply see the updated course; people without a certificate are not touched.
 *
 * Idempotent per person and version: a `policy_reassignments` row is reserved before anything is changed, a rerun
 * resumes people whose assignment is missing and skips the rest, and each notice goes through the notification log.
 * Nothing here decides who completed anything: completion stays the compliance module's, from the course itself.
 */
final class RolloutService
{
    /** The reason a superseded certificate is revoked with; the version number follows. */
    public const REASON_PREFIX = 'policy_superseded:v';

    private const CHUNK = 200;

    public function __construct(
        private readonly CertificateVerificationService $certificates,
        private readonly AssignmentService $assignments,
        private readonly CourseSettings $settings,
        private readonly ExamCourseService $exams,
        private readonly NotificationLog $log,
        private readonly PolicyAudit $audit,
    ) {
    }

    /**
     * Runs the rollout of an approved version once all its department courses are updated. Safe to call again and from
     * several workers: only one runs at a time and a finished version is left alone.
     */
    public function run(PolicyVersion $version): void
    {
        $lock = Cache::lock('policy-rollout:' . $version->id, 900);
        if (!$lock->get()) {
            return;
        }
        try {
            $version->refresh();
            if ($version->status !== PolicyVersion::APPLYING) {
                return;
            }
            $this->execute($version);
        } finally {
            $lock->release();
        }
    }

    private function execute(PolicyVersion $version): void
    {
        $policy = Policy::query()->findOrFail($version->policy_id);
        // the version that was in force before this one: stable across a rerun, when the policy already points at this version
        $previous = PolicyVersion::query()->where('policy_id', $policy->id)->where('number', '<', $version->number)
            ->whereIn('status', [PolicyVersion::APPROVED, PolicyVersion::SUPERSEDED])->orderByDesc('number')->first();
        $approver = User::query()->find($version->approved_by) ?? User::query()->findOrFail($policy->owner_user_id);
        $counts = [];
        $anyCourse = null;
        $summary = $this->summary($version);

        foreach (PolicyRollout::query()->where('policy_version_id', $version->id)->orderBy('id')->get() as $rollout) {
            $variant = PolicyVariant::query()->findOrFail($rollout->variant_id);
            if ($rollout->status === PolicyRollout::NO_CHANGE) {
                // nothing this department's course cites changed: its people keep their certificates and are not retrained
                $rollout->forceFill(['rolled_out_at' => now()])->save();
                $counts[] = ['department' => $variant->department_label, 'revoked' => 0, 'reassigned' => 0];
                continue;
            }
            $course = $this->courseFor($policy, $version, $variant, $approver);
            $anyCourse ??= $course;
            $counts[] = ['department' => $variant->department_label] + $this->rollOut($policy, $version, $previous, $rollout, $variant, $course, $approver, $summary);
        }

        $anyCourse ??= Course::query()->find(PolicyVariant::query()->where('policy_id', $policy->id)->whereNotNull('course_id')->value('course_id'));

        DB::transaction(function () use ($policy, $version, $previous, $counts) {
            $version->forceFill(['status' => PolicyVersion::APPROVED, 'effective_at' => now(), 'rolled_out_at' => now()])->save();
            $previous?->forceFill(['status' => PolicyVersion::SUPERSEDED])->save();
            $policy->forceFill(['current_version_id' => $version->id])->save();
            $this->audit->record('policy.rollout.applied', $policy, $version, ['change_class' => $version->change_class, 'departments' => $counts]);
        });

        $owner = User::query()->find($policy->owner_user_id);
        if ($owner !== null && $anyCourse !== null) {
            $this->log->once('policy_change_approved', 'policy_version', $this->numericId($version->id), 0, (int) $owner->getKey(), fn () => event(new PolicyChangeApproved($owner, $anyCourse, $policy, $version->refresh(), $counts)));
        }
    }

    /** The course a person is given for this change: the exam of the department (minor) or the department course (major). */
    private function courseFor(Policy $policy, PolicyVersion $version, PolicyVariant $variant, User $approver): Course
    {
        if ($version->change_class === PolicyVersion::MAJOR) {
            return Course::query()->findOrFail($variant->course_id);
        }
        $examId = $this->exams->sync($variant, $approver);
        $department = $this->settings->for((int) $variant->course_id);
        $this->settings->save($examId, ['mandatory' => true, 'default_due_days' => $policy->minor_due_days, 'validity_days' => $department->validity_days]);

        return Course::query()->findOrFail($examId);
    }

    /** @return array{revoked:int,reassigned:int} */
    private function rollOut(Policy $policy, PolicyVersion $version, ?PolicyVersion $previous, PolicyRollout $rollout, PolicyVariant $variant, Course $course, User $approver, string $summary): array
    {
        $revoked = 0;
        $reassigned = 0;
        if ($previous !== null) {
            foreach (array_chunk($this->people($previous, $variant, $rollout), self::CHUNK) as $chunk) {
                foreach ($chunk as $userId) {
                    $result = $this->reassign($policy, $version, $previous, $rollout, $variant, $course, $approver, (int) $userId, $summary);
                    $revoked += $result['revoked'];
                    $reassigned += $result['reassigned'];
                }
            }
        }
        $rollout->forceFill(['rolled_out_at' => now(), 'status' => PolicyRollout::APPLIED])->save();

        return ['revoked' => $revoked, 'reassigned' => $reassigned];
    }

    /**
     * Whose training this change supersedes: everyone with a live certificate linked to the previous version of this
     * department, plus anyone a crashed run reserved but did not finish.
     *
     * @return array<int,int> user ids
     */
    private function people(PolicyVersion $previous, PolicyVariant $variant, PolicyRollout $rollout): array
    {
        $linked = PolicyCertificate::query()->where('policy_version_id', $previous->id)->where('variant_id', $variant->id)->get();
        $live = FabricPDF::query()->whereIn('id', $linked->pluck('fabric_pdf_id'))->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->pluck('user_id');
        $resume = PolicyReassignment::query()->where('rollout_id', $rollout->id)->whereNull('assignment_id')->pluck('user_id');

        return $live->merge($resume)->unique()->sort()->values()->map(fn ($id) => (int) $id)->all();
    }

    /** @return array{revoked:int,reassigned:int} */
    private function reassign(Policy $policy, PolicyVersion $version, PolicyVersion $previous, PolicyRollout $rollout, PolicyVariant $variant, Course $course, User $approver, int $userId, string $summary): array
    {
        $user = User::query()->find($userId);
        if ($user === null) {
            return ['revoked' => 0, 'reassigned' => 0];
        }
        $kind = $version->change_class === PolicyVersion::MAJOR ? PolicyReassignment::COURSE : PolicyReassignment::EXAM;
        $row = PolicyReassignment::query()->firstOrCreate(['rollout_id' => $rollout->id, 'user_id' => $userId], ['kind' => $kind]);
        if ($row->assignment_id !== null) {
            return ['revoked' => 0, 'reassigned' => 0];
        }

        $revoked = 0;
        $linked = PolicyCertificate::query()->where('policy_version_id', $previous->id)->where('variant_id', $variant->id)->where('user_id', $userId)->pluck('fabric_pdf_id');
        foreach (FabricPDF::query()->whereIn('id', $linked)->whereNull('revoked_at')->get() as $certificate) {
            $this->certificates->revoke($certificate, $approver, self::REASON_PREFIX . $version->number);
            $row->forceFill(['revoked_certificate_id' => $certificate->getKey()])->save();
            $this->audit->record('policy.certificate.revoked', $policy, $version, ['user_id' => $userId, 'certificate_id' => $certificate->getKey(), 'superseded_version' => $previous->number], $variant->session_id);
            $revoked++;
        }

        $due = now()->addDays($version->change_class === PolicyVersion::MAJOR ? $policy->major_due_days : $policy->minor_due_days);
        $assignment = $this->assignments->assign($user, $course, $due, AssignmentSource::POLICY_CHANGE, $approver);
        $row->forceFill(['assignment_id' => $assignment->getKey(), 'kind' => $kind])->save();
        $this->audit->record('policy.reassigned', $policy, $version, ['user_id' => $userId, 'kind' => $kind, 'assignment_id' => $assignment->getKey(), 'course_id' => $course->getKey()], $variant->session_id);

        $this->log->once('policy_retraining', 'policy_reassignment', (int) $row->getKey(), 0, $userId, function () use ($user, $course, $assignment, $policy, $version, $kind, $summary, $row) {
            event(new PolicyRetrainingAssigned($user, $course, $assignment, $policy, $version, $kind, $summary));
            $row->forceFill(['notified_at' => now()])->save();
        });

        return ['revoked' => $revoked, 'reassigned' => 1];
    }

    /** The source diff of this version in one sentence, stored when the file was submitted. */
    private function summary(PolicyVersion $version): string
    {
        return $version->summary !== null && $version->summary !== '' ? $version->summary : 'The policy text changed.';
    }

    /** NotificationLog keys by integer; a version id (ULID) is reduced to a stable integer. */
    private function numericId(string $ulid): int
    {
        return (int) hexdec(substr(hash('sha256', $ulid), 0, 12));
    }
}
