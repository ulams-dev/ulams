<?php

namespace Ulams\Compliance\Reporting;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ulams\Compliance\Enums\CompliancePermissionsEnum;
use Ulams\Reporting\Datasets\Dataset;
use Ulams\Reporting\Support\Column;
use Ulams\Reporting\Support\ReportContext;

/**
 * Hash-chained Living Course audit entries (the table exists when the living-course package is installed). IP address,
 * user agent and the free-form `data` payload are not part of the dataset, as in the audit export.
 */
class AuditDataset extends Dataset
{
    public function key(): string
    {
        return 'audit';
    }

    public function label(): string
    {
        return 'Living Course audit';
    }

    public function description(): string
    {
        return 'One row per audit entry of the Living Course: who changed which course element when, with the hash chain.';
    }

    public function columns(): array
    {
        return [
            Column::int('id', 'Entry'),
            Column::datetime('created_at', 'When'),
            Column::int('course_id', 'Course id', groupable: true),
            Column::string('action', 'Action'),
            Column::enum('actor_type', 'Actor type', ['user', 'system', 'agent']),
            Column::int('actor_id', 'Actor id', groupable: true),
            Column::string('subject_type', 'Subject type'),
            Column::string('subject_id', 'Subject id', groupable: false),
            Column::int('version_from', 'Version from', groupable: true),
            Column::int('version_to', 'Version to', groupable: true),
            Column::string('prev_hash', 'Previous hash', groupable: false),
            Column::string('hash', 'Hash', groupable: false),
        ];
    }

    public function permission(): string
    {
        return CompliancePermissionsEnum::REPORT;
    }

    public function available(): bool
    {
        return Schema::hasTable('living_course_audit');
    }

    public function baseQuery(ReportContext $context): Builder
    {
        return DB::table('living_course_audit as l')->select([
            'l.id', 'l.created_at', 'l.course_id', 'l.action', 'l.actor_type', 'l.actor_id', 'l.subject_type', 'l.subject_id',
            'l.version_from', 'l.version_to', 'l.prev_hash', 'l.hash',
        ]);
    }
}
