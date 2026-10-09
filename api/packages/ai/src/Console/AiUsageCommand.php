<?php

namespace Ulams\Ai\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Ulams\Ai\Models\AiCall;

/** Prints AI usage of the current tenant per day and task (run with --domain for a tenant). */
class AiUsageCommand extends Command
{
    protected $signature = 'ai:usage {--days=30 : How many days back}';

    protected $description = 'AI calls, tokens and cost per day and task for this tenant';

    public function handle(): int
    {
        $since = Carbon::now()->subDays((int) $this->option('days'))->startOfDay();
        $rows = AiCall::query()
            ->where('created_at', '>=', $since)
            ->select([
                DB::raw('DATE(created_at) AS day'),
                'task',
                DB::raw('COUNT(*) AS calls'),
                DB::raw('SUM(input_tokens) AS input'),
                DB::raw('SUM(output_tokens) AS output'),
                DB::raw('SUM(cache_read_tokens) AS cache_read'),
                DB::raw('SUM(cache_creation_tokens) AS cache_write'),
                DB::raw('SUM(cost_micro_usd) AS cost'),
            ])
            ->groupBy(DB::raw('DATE(created_at)'), 'task')
            ->orderBy('day')
            ->orderBy('task')
            ->get();

        $this->table(
            ['Day', 'Task', 'Calls', 'Input', 'Output', 'Cache read', 'Cache write', 'Cost USD'],
            $rows->map(fn ($r) => [$r->day, $r->task, $r->calls, $r->input, $r->output, $r->cache_read, $r->cache_write, number_format($r->cost / 1000000, 4)])->all(),
        );
        $this->info(sprintf('Total: $%.4f', $rows->sum('cost') / 1000000));

        return self::SUCCESS;
    }
}
