<?php

namespace Ulams\Compliance\Reporting;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Ulams\Compliance\Enums\CompliancePermissionsEnum;
use Ulams\Courses\Models\Course;
use Ulams\Reporting\Datasets\Dataset;
use Ulams\Reporting\Support\Column;
use Ulams\Reporting\Support\ReportContext;

/** Every course certificate with the course version it attests. The columns are the ones the certificates export has. */
class CertificatesDataset extends Dataset
{
    public function key(): string
    {
        return 'certificates';
    }

    public function label(): string
    {
        return 'Certificates';
    }

    public function description(): string
    {
        return 'One row per course certificate: learner, course, validity, revocation and the course version it attests.';
    }

    public function columns(): array
    {
        return [
            Column::string('certificate_id', 'Certificate', groupable: false),
            Column::int('user_id', 'Learner id', groupable: true),
            Column::string('user_name', 'Learner', personal: true),
            Column::string('user_email', 'E-mail', personal: true),
            Column::int('course_id', 'Course id', groupable: true),
            Column::string('course_title', 'Course'),
            Column::datetime('issued_at', 'Issued'),
            Column::datetime('expires_at', 'Expires'),
            Column::enum('status', 'Status', ['valid', 'expired', 'revoked']),
            Column::datetime('revoked_at', 'Revoked'),
            Column::string('revoked_reason', 'Revocation reason'),
            Column::bool('superseded', 'Superseded by a newer certificate'),
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

        $query = DB::table('fabric_pdfs as f')
            ->leftJoin('users as u', fn ($join) => $join->on('u.id', '=', 'f.user_id')->whereNull('u.deleted_at'))
            ->where('f.assignable_type', (new Course())->getMorphClass())
            ->whereNotNull('f.certificate_id');

        foreach ([
            ['f.certificate_id'],
            ['f.user_id'],
            ["CASE WHEN u.id IS NULL THEN NULL ELSE TRIM(COALESCE(u.first_name, '') || ' ' || COALESCE(u.last_name, '')) END as user_name"],
            ['u.email as user_email'],
            ['f.assignable_id as course_id'],
            ["f.vars->>'@VarCourseTitle' as course_title"],
            ['f.created_at as issued_at'],
            ['f.expires_at'],
            ["CASE WHEN f.revoked_at IS NOT NULL THEN 'revoked' WHEN f.expires_at IS NOT NULL AND f.expires_at <= ? THEN 'expired' ELSE 'valid' END as status", [$now]],
            ['f.revoked_at'],
            ['f.revoked_reason'],
            ['(f.superseded_by_id IS NOT NULL) as superseded'],
            ['f.course_version'],
        ] as $column) {
            $query->selectRaw($column[0], $column[1] ?? []);
        }

        return $query;
    }
}
