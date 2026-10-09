<?php

namespace Ulams\LivingCourse\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Ulams\LivingCourse\Jobs\CheckSourceJob;
use Ulams\LivingCourse\Models\Connection;
use Ulams\LivingCourse\Services\CheckLimiter;
use Ulams\LivingCourse\Services\SourceSync;

/**
 * Checks the connections that are due (every 15 minutes, per tenant by the scheduler). Manual and
 * uploaded sources and paused connections are never polled; connections in error back off to daily.
 * Also prunes webhook deliveries older than 30 days.
 */
class PollCommand extends Command
{
    protected $signature = 'living-course:poll';

    protected $description = 'Check the connected sources that are due and prune old webhook deliveries';

    public function handle(CheckLimiter $limiter): int
    {
        $queued = 0;
        $skipped = 0;
        Connection::query()
            ->where('connector', '!=', 'upload')->where('schedule', '!=', 'manual')->where('status', '!=', 'paused')
            ->where(fn ($q) => $q->whereNull('next_check_at')->orWhere('next_check_at', '<=', now()))
            ->orderBy('next_check_at')->limit(500)->get()
            ->each(function (Connection $c) use ($limiter, &$queued, &$skipped) {
                // the next check is planned now, so a slow queue never picks the same connection twice
                $c->forceFill(['next_check_at' => SourceSync::nextCheck($c, $c->status === 'error')])->save();
                if (!$limiter->allow($c)) {
                    $skipped++;

                    return;
                }
                CheckSourceJob::dispatchFor($c->id, 'poll');
                $queued++;
            });
        $pruned = DB::table('living_course_webhook_deliveries')->where('received_at', '<', now()->subDays(30))->delete();
        $this->info("{$queued} check(s) queued, {$skipped} skipped by the daily limit, {$pruned} old webhook deliveries pruned.");

        return self::SUCCESS;
    }
}
