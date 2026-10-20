<?php

namespace Ulams\Tutor\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Deletes conversations past their retention (default 90 days since the last message) with their messages. */
class PruneCommand extends Command
{
    protected $signature = 'tutor:prune';

    protected $description = 'Delete expired tutor conversations';

    public function handle(): int
    {
        $ids = DB::table('tutor_conversations')->where('expires_at', '<', now())->pluck('id');
        foreach ($ids->chunk(500) as $chunk) {
            DB::table('tutor_messages')->whereIn('conversation_id', $chunk)->delete();
            DB::table('tutor_conversations')->whereIn('id', $chunk)->delete();
        }
        $this->info('Pruned ' . $ids->count() . ' conversations.');

        return self::SUCCESS;
    }
}
