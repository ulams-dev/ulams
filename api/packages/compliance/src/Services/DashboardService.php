<?php

namespace Ulams\Compliance\Services;

use Illuminate\Support\Facades\DB;
use Ulams\Compliance\Enums\AssignmentStatus;
use Ulams\Compliance\Models\Assignment;
use Ulams\Courses\Models\Course;
use Ulams\TemplatesPdf\Models\FabricPDF;

/**
 * Numbers for the compliance dashboard: required training (open, overdue, completed, completion rate, time to
 * complete), certificate health (valid, expiring soon, expired, revoked) and the same per course.
 */
class DashboardService
{
    /** @return array<string, mixed> */
    public function summary(?int $courseId = null): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'assignments' => $this->assignments($courseId),
            'certificates' => $this->certificates($courseId),
            'courses' => $this->courses($courseId),
        ];
    }

    private function assignments(?int $courseId): array
    {
        $base = fn () => Assignment::query()->when($courseId, fn ($q) => $q->where('course_id', $courseId));
        $completed = $base()->where('status', AssignmentStatus::COMPLETED)->count();
        $overdue = $base()->open()->where('due_at', '<', now())->count();
        $open = $base()->open()->count();

        return [
            'open' => $open,
            'overdue' => $overdue,
            'completed' => $completed,
            'cancelled' => $base()->where('status', AssignmentStatus::CANCELLED)->count(),
            'completed_last_30_days' => $base()->where('status', AssignmentStatus::COMPLETED)->where('completed_at', '>=', now()->subDays(30))->count(),
            'completion_rate' => $this->rate($completed, $completed + $open),
            'avg_days_to_complete' => $this->avgDays($courseId),
        ];
    }

    private function certificates(?int $courseId): array
    {
        $base = fn () => FabricPDF::query()
            ->where('assignable_type', Course::class)
            ->whereNotNull('certificate_id')
            ->whereNull('superseded_by_id')
            ->when($courseId, fn ($q) => $q->where('assignable_id', $courseId));
        $live = fn () => $base()->whereNull('revoked_at');

        return [
            'valid' => $live()->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count(),
            'expiring_30_days' => $live()->whereBetween('expires_at', [now(), now()->addDays(30)])->count(),
            'expiring_90_days' => $live()->whereBetween('expires_at', [now(), now()->addDays(90)])->count(),
            'expired' => $live()->where('expires_at', '<=', now())->count(),
            'revoked' => $base()->whereNotNull('revoked_at')->count(),
        ];
    }

    /** @return list<array<string, mixed>> courses with assignments, the most overdue first */
    private function courses(?int $courseId): array
    {
        $rows = Assignment::query()
            ->select('course_id', DB::raw('count(*) as total'),
                DB::raw("sum(case when status = 'completed' then 1 else 0 end) as completed"),
                DB::raw("sum(case when status = 'assigned' and due_at < now() then 1 else 0 end) as overdue"),
                DB::raw("sum(case when status = 'assigned' then 1 else 0 end) as open"))
            ->where('status', '!=', AssignmentStatus::CANCELLED)
            ->when($courseId, fn ($q) => $q->where('course_id', $courseId))
            ->groupBy('course_id')
            ->get();
        $titles = Course::query()->whereIn('id', $rows->pluck('course_id'))->pluck('title', 'id');

        return $rows->map(fn ($row) => [
            'course_id' => (int) $row->course_id,
            'title' => $titles[$row->course_id] ?? null,
            'assigned' => (int) $row->total,
            'completed' => (int) $row->completed,
            'open' => (int) $row->open,
            'overdue' => (int) $row->overdue,
            'completion_rate' => $this->rate((int) $row->completed, (int) $row->completed + (int) $row->open),
            'avg_days_to_complete' => $this->avgDays((int) $row->course_id),
        ])->sortByDesc('overdue')->values()->all();
    }

    private function rate(int $done, int $total): ?float
    {
        return $total === 0 ? null : round($done / $total * 100, 1);
    }

    private function avgDays(?int $courseId): ?float
    {
        $days = Assignment::query()
            ->where('status', AssignmentStatus::COMPLETED)
            ->whereNotNull('completed_at')
            ->when($courseId, fn ($q) => $q->where('course_id', $courseId))
            ->get(['assigned_at', 'completed_at'])
            ->map(fn (Assignment $a) => max(0, $a->assigned_at->floatDiffInDays($a->completed_at)));

        return $days->isEmpty() ? null : round($days->avg(), 1);
    }
}
