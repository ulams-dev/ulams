<?php

namespace Ulams\Tutor\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Ulams\Tutor\Streaming\RunLog;

/**
 * Deletes conversations past their retention (default 90 days since the last message) with their messages and
 * run events, and the run events of finished runs one day after they ended (ADR 0105): the stored message is
 * the record, the event log is transport.
 */
class PruneCommand extends Command
{
    protected $signature = 'tutor:prune';

    protected $description = 'Delete expired tutor conversations';

    public function handle(): int
    {
        $ids = DB::table('tutor_conversations')->where('expires_at', '<', now())->pluck('id');
        foreach ($ids->chunk(500) as $chunk) {
            DB::table('tutor_events')->whereIn('conversation_id', $chunk)->delete();
            DB::table('tutor_messages')->whereIn('conversation_id', $chunk)->delete();
            DB::table('tutor_conversations')->whereIn('id', $chunk)->delete();
        }
        $days = max(1, (int) config('tutor.stream.events_retention_days'));
        $before = now()->subDays($days);
        $runs = DB::table('tutor_events')->whereIn('type', RunLog::TERMINAL)->where('created_at', '<', $before)->pluck('run_id');
        $events = 0;
        foreach ($runs->unique()->chunk(500) as $chunk) {
            $events += DB::table('tutor_events')->whereIn('run_id', $chunk)->delete();
        }
        // a run that never ended (queue lost) is not kept either, after the same time plus the retention
        $events += DB::table('tutor_events')->where('created_at', '<', now()->subDays($days + 1))->delete();
        $this->info('Pruned ' . $ids->count() . ' conversations and ' . $events . ' run events.');

        return self::SUCCESS;
    }
}
