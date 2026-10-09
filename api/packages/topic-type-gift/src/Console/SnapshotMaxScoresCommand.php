<?php

namespace Ulams\TopicTypeGift\Console;

use Illuminate\Console\Command;
use Ulams\TopicTypeGift\Models\QuizAttempt;

/**
 * Fills `topic_gift_quiz_attempts.max_score` for ended attempts that predate the snapshot, with the
 * live maximum score at the time of the run (the best value available). Run once per tenant after
 * the migration: `php artisan gift:snapshot-max-scores --domain=<tenant>`. Idempotent.
 */
class SnapshotMaxScoresCommand extends Command
{
    protected $signature = 'gift:snapshot-max-scores';

    protected $description = 'Freeze the maximum score of past quiz attempts (ADR 0033)';

    public function handle(): int
    {
        $count = 0;
        QuizAttempt::query()->whereNull('max_score')->whereNotNull('end_at')->where('end_at', '<=', now())->with('giftQuiz')->chunkById(200, function ($attempts) use (&$count) {
            foreach ($attempts as $attempt) {
                $max = $attempt->giftQuiz?->questions()->sum('score');
                if ($max !== null) {
                    $attempt->forceFill(['max_score' => $max])->save();
                    $count++;
                }
            }
        });
        $this->info("{$count} attempt(s) snapshotted.");

        return self::SUCCESS;
    }
}
