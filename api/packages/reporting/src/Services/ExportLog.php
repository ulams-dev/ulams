<?php

namespace Ulams\Reporting\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Row-level output of personal columns is a read of personal data, so it is recorded the way an audit export is: who,
 * which dataset, what was asked, how many rows. The record lives in `compliance_exports` (compliance owns the table);
 * a tenant without it has no exports to record against.
 */
class ExportLog
{
    /** @param array<string,mixed> $definition */
    public function personalRows(?int $userId, string $dataset, array $definition, int $rows): void
    {
        if (!Schema::hasTable('compliance_exports')) {
            return;
        }
        DB::table('compliance_exports')->insert([
            'user_id' => $userId,
            'dataset' => $dataset,
            'format' => 'json',
            'filters' => json_encode(['report' => $definition]),
            'row_count' => $rows,
            'created_at' => now(),
        ]);
    }

    /**
     * An export of a report (CSV or JSON lines): always recorded, as the compliance exports are.
     *
     * @param array<string,mixed> $definition
     */
    public function export(?int $userId, string $dataset, array $definition, string $format, int $rows, ?string $savedReportId, string $channel): void
    {
        if (!Schema::hasTable('compliance_exports')) {
            return;
        }
        $row = ['user_id' => $userId, 'dataset' => $dataset, 'format' => $format, 'filters' => json_encode(['report' => $definition]), 'row_count' => $rows, 'created_at' => now()];
        if (Schema::hasColumn('compliance_exports', 'channel')) {
            $row += ['saved_report_id' => $savedReportId, 'channel' => $channel];
        }
        DB::table('compliance_exports')->insert($row);
    }
}
