<?php

namespace Ulams\Compliance\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ulams\Compliance\Models\Assignment;
use Ulams\Courses\Models\Course;
use Ulams\TemplatesPdf\Models\FabricPDF;

/**
 * Compliance datasets: who was required to do what and what came of it (`assignments`), the
 * certificates themselves with the course version they attest (`certificates`), and the
 * hash-chained Living Course audit entries of the courses involved (`audit`, when that package is
 * installed). The same rows feed the JSON report and the CSV export.
 */
class ComplianceReportService
{
    public const DATASETS = ['assignments', 'certificates', 'audit'];

    private const COLUMNS = [
        'assignments' => ['assignment_id', 'user_id', 'user_name', 'user_email', 'course_id', 'course_title', 'source', 'status', 'assigned_at', 'due_at', 'completed_at', 'days_overdue', 'escalation_level', 'certificate_id', 'certificate_status', 'certificate_issued_at', 'certificate_expires_at', 'course_version'],
        'certificates' => ['certificate_id', 'user_id', 'user_name', 'user_email', 'course_id', 'course_title', 'issued_at', 'expires_at', 'status', 'revoked_at', 'revoked_reason', 'superseded', 'course_version'],
        'audit' => ['id', 'created_at', 'course_id', 'action', 'actor_type', 'actor_id', 'subject_type', 'subject_id', 'version_from', 'version_to', 'prev_hash', 'hash'],
    ];

    public function available(string $dataset): bool
    {
        return in_array($dataset, self::DATASETS, true) && ($dataset !== 'audit' || Schema::hasTable('living_course_audit'));
    }

    /** @return list<string> */
    public function columns(string $dataset): array
    {
        return self::COLUMNS[$dataset];
    }

    /** @return array<string, mixed> */
    public function normalise(array $input): array
    {
        return array_filter([
            'course_id' => isset($input['course_id']) ? (int) $input['course_id'] : null,
            'user_id' => isset($input['user_id']) ? (int) $input['user_id'] : null,
            'status' => $input['status'] ?? null,
            'from' => $input['from'] ?? null,
            'to' => $input['to'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    public function query(string $dataset, array $filters): Builder|\Illuminate\Database\Query\Builder
    {
        return match ($dataset) {
            'assignments' => $this->assignments($filters),
            'certificates' => $this->certificates($filters),
            'audit' => $this->audit($filters),
        };
    }

    /** One row of the dataset for a model or a database row of `query()`. */
    public function row(string $dataset, object $record): array
    {
        return match ($dataset) {
            'assignments' => $this->assignmentRow($record),
            'certificates' => $this->certificateRow($record),
            'audit' => $this->auditRow($record),
        };
    }

    private function assignments(array $filters): Builder
    {
        $query = Assignment::query()->with(['user', 'course', 'certificate'])->orderBy('id');
        $this->common($query, $filters, 'course_id', 'user_id', 'assigned_at');
        match ($filters['status'] ?? null) {
            'overdue' => $query->open()->where('due_at', '<', now()),
            'assigned' => $query->open()->where(fn (Builder $q) => $q->whereNull('due_at')->orWhere('due_at', '>=', now())),
            'completed', 'cancelled' => $query->where('status', $filters['status']),
            default => null,
        };

        return $query;
    }

    private function certificates(array $filters): Builder
    {
        $query = FabricPDF::query()->with('user')->where('assignable_type', Course::class)->whereNotNull('certificate_id')->orderBy('id');
        $this->common($query, $filters, 'assignable_id', 'user_id', 'created_at');
        match ($filters['status'] ?? null) {
            'revoked' => $query->whereNotNull('revoked_at'),
            'expired' => $query->whereNull('revoked_at')->where('expires_at', '<=', now()),
            'valid' => $query->whereNull('revoked_at')->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now())),
            default => null,
        };

        return $query;
    }

    private function audit(array $filters): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('living_course_audit')->orderBy('id');
        $this->common($query, $filters, 'course_id', null, 'created_at');

        return $query;
    }

    private function common(Builder|\Illuminate\Database\Query\Builder $query, array $filters, string $courseColumn, ?string $userColumn, string $dateColumn): void
    {
        if (isset($filters['course_id'])) {
            $query->where($courseColumn, $filters['course_id']);
        }
        if ($userColumn && isset($filters['user_id'])) {
            $query->where($userColumn, $filters['user_id']);
        }
        if (isset($filters['from'])) {
            $query->where($dateColumn, '>=', Carbon::parse($filters['from'])->startOfDay());
        }
        if (isset($filters['to'])) {
            $query->where($dateColumn, '<=', Carbon::parse($filters['to'])->endOfDay());
        }
    }

    private function assignmentRow(Assignment $assignment): array
    {
        $certificate = $assignment->certificate;

        return [
            'assignment_id' => $assignment->getKey(),
            'user_id' => $assignment->user_id,
            'user_name' => $assignment->user?->name,
            'user_email' => $assignment->user?->email,
            'course_id' => $assignment->course_id,
            'course_title' => $assignment->course?->title,
            'source' => $assignment->source,
            'status' => $assignment->displayStatus(),
            'assigned_at' => $this->date($assignment->assigned_at),
            'due_at' => $this->date($assignment->due_at),
            'completed_at' => $this->date($assignment->completed_at),
            'days_overdue' => $assignment->isOverdue() ? (int) floor($assignment->due_at->diffInDays(now(), true)) : 0,
            'escalation_level' => $assignment->escalation_level,
            'certificate_id' => $certificate?->certificate_id,
            'certificate_status' => $certificate?->status(),
            'certificate_issued_at' => $this->date($certificate?->created_at),
            'certificate_expires_at' => $this->date($certificate?->expires_at),
            'course_version' => $certificate?->course_version,
        ];
    }

    private function certificateRow(FabricPDF $certificate): array
    {
        return [
            'certificate_id' => $certificate->certificate_id,
            'user_id' => $certificate->user_id,
            'user_name' => $certificate->user?->name,
            'user_email' => $certificate->user?->email,
            'course_id' => $certificate->assignable_id,
            'course_title' => $certificate->vars['@VarCourseTitle'] ?? null,
            'issued_at' => $this->date($certificate->created_at),
            'expires_at' => $this->date($certificate->expires_at),
            'status' => $certificate->status(),
            'revoked_at' => $this->date($certificate->revoked_at),
            'revoked_reason' => $certificate->revoked_reason,
            'superseded' => $certificate->superseded_by_id !== null,
            'course_version' => $certificate->course_version,
        ];
    }

    private function auditRow(object $entry): array
    {
        return [
            'id' => $entry->id,
            'created_at' => $entry->created_at,
            'course_id' => $entry->course_id,
            'action' => $entry->action,
            'actor_type' => $entry->actor_type,
            'actor_id' => $entry->actor_id,
            'subject_type' => $entry->subject_type,
            'subject_id' => $entry->subject_id,
            'version_from' => $entry->version_from,
            'version_to' => $entry->version_to,
            'prev_hash' => $entry->prev_hash,
            'hash' => $entry->hash,
        ];
    }

    private function date(?\DateTimeInterface $date): ?string
    {
        return $date ? Carbon::instance($date)->utc()->toIso8601String() : null;
    }
}
