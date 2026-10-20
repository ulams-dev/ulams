<?php

namespace Ulams\Compliance\Services;

use Carbon\CarbonInterface;
use Ulams\Compliance\Events\CertificateExpired;
use Ulams\Compliance\Events\CertificateExpiringSoon;
use Ulams\Compliance\Events\TrainingDueSoon;
use Ulams\Compliance\Events\TrainingEscalated;
use Ulams\Compliance\Events\TrainingOverdue;
use Ulams\Compliance\Models\Assignment;
use Ulams\Courses\Models\Course;
use Ulams\TemplatesPdf\Models\FabricPDF;

/**
 * The daily run: certificate reminders and expiry, recertification, due-soon and overdue notices
 * and manager escalation. Every notice goes through `NotificationLog::once`, so running it again
 * (or late) never sends the same one twice. When several reminder offsets have passed, only the most
 * urgent one is sent.
 */
class DeadlineProcessor
{
    /** Expiry notices older than this are not sent (a first run on an old installation stays quiet). */
    private const EXPIRED_NOTICE_DAYS = 7;

    public function __construct(
        private CourseSettings $settings,
        private NotificationLog $log,
        private ManagerService $managers,
        private RecertificationService $recertification,
    ) {
    }

    /** @return array<string, int> counts per kind */
    public function process(?CarbonInterface $now = null): array
    {
        $now ??= now();
        $this->settings->forget();

        return [
            'recertifications' => $this->recertification->run($now),
            'certificate_reminders' => $this->certificateReminders($now),
            'certificates_expired' => $this->certificatesExpired($now),
            'due_reminders' => $this->dueReminders($now),
            'overdue' => $this->overdue($now),
            'escalations' => $this->escalations($now),
        ];
    }

    private function certificateReminders(CarbonInterface $now): int
    {
        $sent = 0;
        $this->currentCertificates()->where('expires_at', '>', $now)->each(function (FabricPDF $certificate) use ($now, &$sent) {
            $offset = $this->reminderOffset($this->settings->for((int) $certificate->assignable_id)->reminderDays(), $certificate->expires_at, $now);
            $course = Course::find($certificate->assignable_id);
            if ($offset === null || !$course || !$certificate->user) {
                return;
            }
            $daysLeft = max(0, (int) ceil($now->floatDiffInDays($certificate->expires_at)));
            $sent += (int) $this->log->once('certificate_expiring', 'certificate', $certificate->getKey(), $offset, $certificate->user_id, fn () => event(new CertificateExpiringSoon($certificate->user, $course, $certificate, $daysLeft)));
        });

        return $sent;
    }

    private function certificatesExpired(CarbonInterface $now): int
    {
        $sent = 0;
        $this->currentCertificates()
            ->where('expires_at', '<=', $now)
            ->where('expires_at', '>=', $now->copy()->subDays(self::EXPIRED_NOTICE_DAYS))
            ->each(function (FabricPDF $certificate) use (&$sent) {
                $course = Course::find($certificate->assignable_id);
                if (!$course || !$certificate->user) {
                    return;
                }
                $sent += (int) $this->log->once('certificate_expired', 'certificate', $certificate->getKey(), 0, $certificate->user_id, fn () => event(new CertificateExpired($certificate->user, $course, $certificate)));
            });

        return $sent;
    }

    private function dueReminders(CarbonInterface $now): int
    {
        $sent = 0;
        Assignment::query()->open()->where('due_at', '>', $now)->with(['user', 'course'])->each(function (Assignment $assignment) use ($now, &$sent) {
            $offset = $this->reminderOffset($this->settings->for($assignment->course_id)->reminderDays(), $assignment->due_at, $now);
            if ($offset === null || !$assignment->user || !$assignment->course) {
                return;
            }
            $daysLeft = max(0, (int) ceil($now->floatDiffInDays($assignment->due_at)));
            $sent += (int) $this->log->once('training_due_soon', 'assignment', $assignment->getKey(), $offset, $assignment->user_id, fn () => event(new TrainingDueSoon($assignment->user, $assignment->course, $assignment, $daysLeft)));
        });

        return $sent;
    }

    private function overdue(CarbonInterface $now): int
    {
        $sent = 0;
        Assignment::query()->open()->where('due_at', '<=', $now)->with(['user', 'course'])->each(function (Assignment $assignment) use (&$sent) {
            if (!$assignment->user || !$assignment->course) {
                return;
            }
            $sent += (int) $this->log->once('training_overdue', 'assignment', $assignment->getKey(), 0, $assignment->user_id, fn () => event(new TrainingOverdue($assignment->user, $assignment->course, $assignment)));
        });

        return $sent;
    }

    private function escalations(CarbonInterface $now): int
    {
        $sent = 0;
        Assignment::query()->open()->where('due_at', '<=', $now)->with(['user', 'course'])->each(function (Assignment $assignment) use ($now, &$sent) {
            $levels = $this->settings->for($assignment->course_id)->escalationDays();
            $reached = array_values(array_filter($levels, fn (int $days) => $assignment->due_at->copy()->addDays($days)->lte($now)));
            if ($reached === [] || !$assignment->user || !$assignment->course) {
                return;
            }
            $manager = $this->managers->managerOf($assignment->user_id);
            if (!$manager) {
                return; // retried on the next run, once a manager is linked
            }
            $offset = max($reached);
            $level = array_search($offset, $levels, true) + 1;
            $sent += (int) $this->log->once('training_escalated', 'assignment', $assignment->getKey(), $offset, $manager->getKey(), function () use ($assignment, $manager, $level) {
                $assignment->forceFill(['escalation_level' => $level])->save();
                event(new TrainingEscalated($manager, $assignment->course, $assignment, $assignment->user));
            });
        });

        return $sent;
    }

    /** Certificates that still matter: course certificates that are neither revoked nor replaced. */
    private function currentCertificates()
    {
        return FabricPDF::query()
            ->with('user')
            ->where('assignable_type', Course::class)
            ->whereNull('revoked_at')
            ->whereNull('superseded_by_id')
            ->whereNotNull('expires_at');
    }

    /**
     * The smallest configured offset (days before) that has been reached, or null when none has.
     *
     * @param list<int> $offsets
     */
    private function reminderOffset(array $offsets, CarbonInterface $date, CarbonInterface $now): ?int
    {
        $reached = array_filter($offsets, fn (int $days) => $date->copy()->subDays($days)->lte($now));

        return $reached === [] ? null : min($reached);
    }
}
