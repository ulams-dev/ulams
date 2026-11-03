<?php

namespace Ulams\Commerce\Console;

use Illuminate\Console\Command;
use Ulams\Commerce\Services\WellmsAccessBackfill;
use Ulams\Entitlements\Models\ParityReport;

/**
 * Backfills entitlements for access that exists today (ADR 0130, section 6.3). `--dry-run` computes everything,
 * stores the report and changes nothing. A real run is all-or-nothing: if any (user, course) pair would differ
 * afterwards, nothing is written. Safe to run again.
 */
class BackfillAccessCommand extends Command
{
    protected $signature = 'ulams:entitlements:backfill {--dry-run : Compute and report, write nothing}';

    protected $description = 'Give every existing course_user row an entitlement and prove that no access changed';

    public function handle(WellmsAccessBackfill $backfill): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $report = $backfill->run($dryRun);
        $details = $report->details ?? [];

        $would = $details['would_create'] ?? [];
        $rows = $would['course_user_rows'] ?? [];
        $this->line(sprintf(
            '%s: %d course_user row(s) -> %d wellms_order + %d migration entitlement(s), %d subscription entitlement(s); %d subscription(s) without a course row skipped.',
            $dryRun ? 'Dry run' : 'Backfill',
            array_sum($rows), $rows['wellms_order'] ?? 0, $rows['migration'] ?? 0, $would['subscriptions'] ?? 0, $details['subscriptions_without_course_row'] ?? 0,
        ));
        $this->line("Parity report #{$report->id}: {$report->pairs_checked} pair(s) checked, {$report->differences} difference(s).");
        foreach (array_slice($details['sample'] ?? [], 0, 10) as $row) {
            $this->line("  user {$row['user_id']} course {$row['course_id']}: {$row['kind']}");
        }

        if ($report->status === ParityReport::STATUS_FAILED) {
            $this->error('Parity failed: nothing was written.');

            return self::FAILURE;
        }
        $this->info($dryRun ? 'Parity holds; a real run would write the entitlements above.' : "Created {$report->entitlements_created} entitlement(s); parity holds.");

        return self::SUCCESS;
    }
}
