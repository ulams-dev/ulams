<?php

namespace Ulams\PolicyLifecycle\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Ulams\Compliance\Enums\AssignmentStatus;
use Ulams\Compliance\Models\Assignment;
use Ulams\CourseBuilder\Models\Release;
use Ulams\PolicyLifecycle\Models\Policy;
use Ulams\PolicyLifecycle\Models\PolicyCertificate;
use Ulams\PolicyLifecycle\Models\PolicyVariant;
use Ulams\PolicyLifecycle\Models\PolicyVersion;

/**
 * The policy owner's view (plan 4.3, "Owner dashboard"): per department how many people are assigned, completed,
 * pending and overdue, and per person the course version they took, the policy version their certificate certifies
 * and the certificate itself. Read-only; it counts assignments of the department courses whatever created them.
 */
final class DashboardService
{
    public const STATUSES = ['assigned', 'overdue', 'completed', 'cancelled'];

    /**
     * @return array<int,array{id:string,department:string,groupId:?int,courseId:?int,stage:string,assigned:int,completed:int,pending:int,overdue:int,completionRate:?float}>
     */
    public function departments(Policy $policy, VariantGenerator $generator): array
    {
        $rows = [];
        foreach ($policy->variants as $variant) {
            $query = $this->assignments($variant);
            $assigned = (clone $query)->where('status', '!=', AssignmentStatus::CANCELLED)->count();
            $completed = (clone $query)->where('status', AssignmentStatus::COMPLETED)->count();
            $overdue = (clone $query)->open()->where('due_at', '<', now())->count();
            $open = (clone $query)->open()->count();
            $rows[] = [
                'id' => $variant->id,
                'department' => $variant->department_label,
                'groupId' => $variant->group_id,
                'courseId' => $variant->course_id,
                'stage' => $generator->stage($variant),
                'assigned' => $assigned,
                'completed' => $completed,
                'pending' => $open - $overdue,
                'overdue' => $overdue,
                'completionRate' => $assigned > 0 ? round($completed / $assigned, 4) : null,
            ];
        }

        return $rows;
    }

    /**
     * @param array{variant?:?string,status?:?string} $filters
     *
     * @return Builder<Assignment>
     */
    public function peopleQuery(Policy $policy, array $filters): Builder
    {
        $variants = $policy->variants->filter(fn (PolicyVariant $v) => $v->course_id !== null)
            ->when(($filters['variant'] ?? null) !== null, fn ($c) => $c->filter(fn (PolicyVariant $v) => $v->id === $filters['variant']));
        $query = Assignment::query()->with(['user', 'certificate'])->latest('id')->where(function (Builder $any) use ($variants) {
            $any->whereRaw('1 = 0');
            foreach ($variants as $variant) {
                $any->orWhereIn('id', $this->latestIds(array_values(array_filter([$variant->course_id, $variant->exam_course_id]))));
            }
        });
        match ($filters['status'] ?? null) {
            'overdue' => $query->open()->where('due_at', '<', now()),
            AssignmentStatus::ASSIGNED => $query->open()->where(fn (Builder $q) => $q->whereNull('due_at')->orWhere('due_at', '>=', now())),
            AssignmentStatus::COMPLETED, AssignmentStatus::CANCELLED => $query->where('status', $filters['status']),
            default => null,
        };

        return $query;
    }

    /**
     * @param iterable<Assignment> $assignments
     *
     * @return array<int,array<string,mixed>>
     */
    public function people(Policy $policy, iterable $assignments): array
    {
        $byCourse = collect();
        foreach ($policy->variants as $v) {
            foreach (array_filter([$v->course_id, $v->exam_course_id]) as $courseId) {
                $byCourse[$courseId] = $v;
            }
        }
        $versions = PolicyVersion::query()->where('policy_id', $policy->id)->pluck('number', 'id');
        $links = PolicyCertificate::query()->whereIn('fabric_pdf_id', collect($assignments)->pluck('certificate_id')->filter()->all() ?: [0])->pluck('policy_version_id', 'fabric_pdf_id');
        $releases = [];
        $rows = [];
        foreach ($assignments as $assignment) {
            $variant = $byCourse[$assignment->course_id] ?? null;
            $certificate = $assignment->certificate;
            $rows[] = [
                'assignmentId' => $assignment->getKey(),
                'user' => $assignment->user ? ['id' => $assignment->user->getKey(), 'name' => $assignment->user->name, 'email' => $assignment->user->email] : null,
                'department' => $variant?->department_label,
                'variantId' => $variant?->id,
                'courseId' => $assignment->course_id,
                'courseVersion' => $variant !== null && (int) $assignment->course_id === (int) $variant->course_id ? $this->release($releases, $variant, $assignment) : null,
                'exam' => $variant !== null && $variant->exam_course_id !== null && (int) $assignment->course_id === (int) $variant->exam_course_id,
                'status' => $assignment->displayStatus(),
                'source' => $assignment->source,
                'assignedAt' => $assignment->assigned_at?->toIso8601String(),
                'dueAt' => $assignment->due_at?->toIso8601String(),
                'completedAt' => $assignment->completed_at?->toIso8601String(),
                'policyVersion' => $certificate !== null && isset($links[$certificate->getKey()]) ? ($versions[$links[$certificate->getKey()]] ?? null) : null,
                'certificate' => $certificate !== null ? [
                    'id' => $certificate->getKey(),
                    'certificateId' => $certificate->certificate_id,
                    'status' => $certificate->status(),
                    'expiresAt' => $certificate->expires_at?->toIso8601String(),
                    'verifyPath' => '/api/certificates/verify/' . $certificate->certificate_id,
                ] : null,
            ];
        }

        return $rows;
    }

    public function paginate(Policy $policy, array $filters, int $perPage): LengthAwarePaginator
    {
        $page = $this->peopleQuery($policy, $filters)->paginate($perPage);
        $page->setCollection(collect($this->people($policy, $page->items())));

        return $page;
    }

    /**
     * The department's assignments that stand now: each person's latest assignment of the department course or of its
     * exam course (after a minor change the exam replaces the course as what the person owes).
     *
     * @return Builder<Assignment>
     */
    private function assignments(PolicyVariant $variant): Builder
    {
        return Assignment::query()->whereIn('id', $this->latestIds(array_values(array_filter([$variant->course_id, $variant->exam_course_id]))));
    }

    /**
     * @param array<int,int> $courseIds
     *
     * @return Builder<Assignment> one row per person: the id of their latest assignment of these courses
     */
    private function latestIds(array $courseIds): Builder
    {
        return Assignment::query()->selectRaw('MAX(id)')->whereIn('course_id', $courseIds ?: [0])->groupBy('user_id');
    }

    /**
     * The course release a person took: the latest one released before they finished, or the current one while open.
     *
     * @param array<string,\Illuminate\Support\Collection<int,Release>> $cache
     */
    private function release(array &$cache, PolicyVariant $variant, Assignment $assignment): ?int
    {
        $cache[$variant->id] ??= $variant->session_id === null ? collect() : Release::query()->where('session_id', $variant->session_id)->whereNull('variant_id')->orderBy('number')->get();
        $releases = $cache[$variant->id];
        if ($assignment->completed_at !== null) {
            $release = $releases->filter(fn (Release $r) => $r->released_at <= $assignment->completed_at)->last();
            if ($release !== null) {
                return $release->number;
            }
        }

        return $releases->last()?->number;
    }
}
