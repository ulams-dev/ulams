<?php

namespace Ulams\Tutor\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Ulams\Tutor\Services\TutorIndexer;

class IndexCommand extends Command
{
    protected $signature = 'tutor:index {--course= : Only this course id}';

    protected $description = 'Rebuild the tutor search index of one course or of every course';

    public function handle(TutorIndexer $indexer): int
    {
        $ids = $this->option('course') ? [(int) $this->option('course')] : DB::table('courses')->pluck('id')->all();
        $chunks = 0;
        foreach ($ids as $id) {
            $chunks += $indexer->rebuild((int) $id);
        }
        $this->info('Indexed ' . $chunks . ' chunks in ' . count($ids) . ' courses.');

        return self::SUCCESS;
    }
}
