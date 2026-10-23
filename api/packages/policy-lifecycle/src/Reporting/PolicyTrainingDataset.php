<?php

namespace Ulams\PolicyLifecycle\Reporting;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ulams\PolicyLifecycle\Enums\PolicyPermissionsEnum;
use Ulams\Reporting\Datasets\Dataset;
use Ulams\Reporting\Support\Column;
use Ulams\Reporting\Support\ReportContext;

/**
 * Who is trained on which policy (the `policy_training` dataset of the report registry): one row per person and
 * department with the person's latest assignment, the policy version their certificate certifies and the certificate.
 * The people of the owner dashboard, for report builders and BI tools. A viewer with `policy_manage` sees every policy;
 * one with only `policy_owner` sees the policies they own.
 */
class PolicyTrainingDataset extends Dataset
{
    public function key(): string
    {
        return 'policy_training';
    }

    public function label(): string
    {
        return 'Policy training';
    }

    public function description(): string
    {
        return 'One row per person and department of a policy: the latest assignment, its outcome, the policy version the certificate certifies and the certificate.';
    }

    public function columns(): array
    {
        return [
            Column::string('policy_id', 'Policy id', groupable: true),
            Column::string('policy_title', 'Policy'),
            Column::string('department', 'Department'),
            Column::int('user_id', 'Learner id', groupable: true),
            Column::string('user_name', 'Learner', personal: true),
            Column::string('user_email', 'E-mail', personal: true),
            Column::int('course_id', 'Course id', groupable: true),
            Column::enum('status', 'Status', ['assigned', 'overdue', 'completed', 'cancelled']),
            Column::datetime('assigned_at', 'Assigned'),
            Column::datetime('due_at', 'Due'),
            Column::datetime('completed_at', 'Completed'),
            Column::int('policy_version', 'Policy version certified', groupable: true),
            Column::string('certificate_id', 'Certificate', groupable: false),
            Column::enum('certificate_status', 'Certificate status', ['valid', 'expired', 'revoked']),
        ];
    }

    public function permission(): string
    {
        return PolicyPermissionsEnum::OWNER;
    }

    public function subjectColumn(): ?string
    {
        return 'user_id';
    }

    public function available(): bool
    {
        return Schema::hasTable('policy_variants') && Schema::hasTable('compliance_assignments');
    }

    public function baseQuery(ReportContext $context): Builder
    {
        $now = now();
        $query = DB::table('policy_variants as v')
            ->join('policies as p', 'p.id', '=', 'v.policy_id')
            ->join('compliance_assignments as a', fn ($join) => $join->on('a.course_id', '=', 'v.course_id'))
            ->leftJoin('users as u', fn ($join) => $join->on('u.id', '=', 'a.user_id')->whereNull('u.deleted_at'))
            ->leftJoin('fabric_pdfs as f', 'f.id', '=', 'a.certificate_id')
            ->leftJoin('policy_certificates as pc', 'pc.fabric_pdf_id', '=', 'f.id')
            ->leftJoin('policy_versions as pv', 'pv.id', '=', 'pc.policy_version_id')
            // each person once per department: the latest assignment of the department's course
            ->whereRaw('a.id = (SELECT MAX(a2.id) FROM compliance_assignments a2 WHERE a2.user_id = a.user_id AND a2.course_id = v.course_id)');
        // an owner without `policy_manage` sees only their own policies; no viewer sees nothing
        if (!$context->can(PolicyPermissionsEnum::MANAGE)) {
            $query->where('p.owner_user_id', $context->viewer?->getAuthIdentifier() ?? 0);
        }

        foreach ([
            ['p.id as policy_id'],
            ['p.title as policy_title'],
            ['v.department_label as department'],
            ['a.user_id'],
            ["CASE WHEN u.id IS NULL THEN NULL ELSE TRIM(COALESCE(u.first_name, '') || ' ' || COALESCE(u.last_name, '')) END as user_name"],
            ['u.email as user_email'],
            ['a.course_id'],
            ["CASE WHEN a.status = 'assigned' AND a.due_at IS NOT NULL AND a.due_at < ? THEN 'overdue' ELSE a.status END as status", [$now]],
            ['a.assigned_at'],
            ['a.due_at'],
            ['a.completed_at'],
            ['pv.number as policy_version'],
            ['f.certificate_id'],
            ["CASE WHEN f.id IS NULL THEN NULL WHEN f.revoked_at IS NOT NULL THEN 'revoked' WHEN f.expires_at IS NOT NULL AND f.expires_at <= ? THEN 'expired' ELSE 'valid' END as certificate_status", [$now]],
        ] as $column) {
            $query->selectRaw($column[0], $column[1] ?? []);
        }

        return $query;
    }
}
