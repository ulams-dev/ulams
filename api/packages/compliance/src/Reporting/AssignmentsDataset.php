<?php

namespace Ulams\Compliance\Reporting;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Ulams\Compliance\Enums\AssignmentSource;
use Ulams\Compliance\Enums\CompliancePermissionsEnum;
use Ulams\Reporting\Datasets\Dataset;
use Ulams\Reporting\Support\Column;
use Ulams\Reporting\Support\ReportContext;

/**
 * Required training and what came of it: one row per assignment with the learner, the course and the certificate it
 * produced. The column list is the one `ComplianceReportService` exports, in the same order.
 */
class AssignmentsDataset extends Dataset
{
    public function key(): string
    {
        return 'assignments';
    }

    public function label(): string
    {
        return 'Training assignments';
    }

    public function description(): string
    {
        return 'One row per required-training assignment: who, which course, due date, outcome and the certificate it produced.';
    }

    public function columns(): array
    {
        return [
            Column::int('assignment_id', 'Assignment'),
            Column::int('user_id', 'Learner id', groupable: true),
            Column::string('user_name', 'Learner', personal: true),
            Column::string('user_email', 'E-mail', personal: true),
            Column::int('course_id', 'Course id', groupable: true),
            Column::string('course_title', 'Course'),
            Column::enum('source', 'Assigned by', AssignmentSource::ALL),
            Column::enum('status', 'Status', ['assigned', 'overdue', 'completed', 'cancelled']),
            Column::datetime('assigned_at', 'Assigned'),
            Column::datetime('due_at', 'Due'),
            Column::datetime('completed_at', 'Completed'),
            Column::int('days_overdue', 'Days overdue', groupable: true),
            Column::int('escalation_level', 'Escalation level', groupable: true),
            Column::string('certificate_id', 'Certificate', groupable: false),
            Column::enum('certificate_status', 'Certificate status', ['valid', 'expired', 'revoked']),
            Column::datetime('certificate_issued_at', 'Certificate issued'),
            Column::datetime('certificate_expires_at', 'Certificate expires'),
            Column::string('course_version', 'Course version'),
        ];
    }

    public function permission(): string
    {
        return CompliancePermissionsEnum::REPORT;
    }

    public function subjectColumn(): ?string
    {
        return 'user_id';
    }

    public function baseQuery(ReportContext $context): Builder
    {
        $now = now();

        $query = DB::table('compliance_assignments as a')
            ->leftJoin('users as u', fn ($join) => $join->on('u.id', '=', 'a.user_id')->whereNull('u.deleted_at'))
            ->leftJoin('courses as c', 'c.id', '=', 'a.course_id')
            ->leftJoin('fabric_pdfs as f', 'f.id', '=', 'a.certificate_id');

        // one selectRaw per column keeps the declared order; the clock is a binding like any other value
        foreach ([
            ['a.id as assignment_id'],
            ['a.user_id'],
            ["CASE WHEN u.id IS NULL THEN NULL ELSE TRIM(COALESCE(u.first_name, '') || ' ' || COALESCE(u.last_name, '')) END as user_name"],
            ['u.email as user_email'],
            ['a.course_id'],
            ['c.title as course_title'],
            ['a.source'],
            ["CASE WHEN a.status = 'assigned' AND a.due_at IS NOT NULL AND a.due_at < ? THEN 'overdue' ELSE a.status END as status", [$now]],
            ['a.assigned_at'],
            ['a.due_at'],
            ['a.completed_at'],
            ["CASE WHEN a.status = 'assigned' AND a.due_at IS NOT NULL AND a.due_at < ? THEN CAST(FLOOR(EXTRACT(EPOCH FROM (CAST(? AS TIMESTAMP) - a.due_at)) / 86400) AS INTEGER) ELSE 0 END as days_overdue", [$now, $now]],
            ['a.escalation_level'],
            ['f.certificate_id'],
            ["CASE WHEN f.id IS NULL THEN NULL WHEN f.revoked_at IS NOT NULL THEN 'revoked' WHEN f.expires_at IS NOT NULL AND f.expires_at <= ? THEN 'expired' ELSE 'valid' END as certificate_status", [$now]],
            ['f.created_at as certificate_issued_at'],
            ['f.expires_at as certificate_expires_at'],
            ['f.course_version'],
        ] as $column) {
            $query->selectRaw($column[0], $column[1] ?? []);
        }

        return $query;
    }
}
