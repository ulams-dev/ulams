<?php

namespace Ulams\Entitlements\Console;

use Illuminate\Console\Command;
use Ulams\Entitlements\Models\ParityReport;
use Ulams\Entitlements\Support\AccessParity;

/**
 * Compares `course_user` with the entitlements, stores the report and fails when any pair differs (ADR 0130,
 * section 6.3). Read-only: it never repairs. The run before a tenant's cutover (CM-10) must show zero differences.
 */
class ParityCommand extends Command
{
    protected $signature = 'ulams:entitlements:parity {--latest : Print the last stored report instead of running a check}';

    protected $description = 'Check that course_user and the entitlements give every learner the same access';

    public function handle(AccessParity $parity): int
    {
        if ($this->option('latest')) {
            $report = ParityReport::query()->latest('id')->first();
            if ($report === null) {
                $this->warn('No parity report stored yet.');

                return self::FAILURE;
            }

            return $this->print($report);
        }

        $before = $parity->snapshot();
        $result = $parity->check((int) config('entitlements.parity.sample'));
        $report = ParityReport::query()->create([
            'kind' => ParityReport::KIND_CHECK,
            'status' => $result->passed() ? ParityReport::STATUS_PASSED : ParityReport::STATUS_FAILED,
            'pairs_checked' => $result->pairs,
            'differences' => $result->differences,
            'details' => $result->details(),
            'snapshot_before' => $before['course_user'],
            'snapshot_after' => $before['course_user'],
        ]);

        return $this->print($report);
    }

    private function print(ParityReport $report): int
    {
        $this->line("Parity report #{$report->id} ({$report->kind}, {$report->created_at}): {$report->pairs_checked} pair(s) checked, {$report->differences} difference(s).");
        foreach (($report->details['by_kind'] ?? []) as $kind => $count) {
            $this->line("  {$kind}: {$count}");
        }
        foreach (array_slice($report->details['sample'] ?? [], 0, 10) as $row) {
            $this->line("  user {$row['user_id']} course {$row['course_id']}: {$row['kind']}");
        }

        return $report->status === ParityReport::STATUS_PASSED ? self::SUCCESS : self::FAILURE;
    }
}
