<?php

namespace Ulams\CodeExercises\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Ulams\CodeExercises\Enums\CompletionRule;
use Ulams\CodeExercises\Events\CodeAttemptFinished;
use Ulams\CodeExercises\Models\CodeExerciseAttempt;
use Ulams\CodeExercises\Models\CodeExerciseTopic;
use Ulams\Courses\Enum\ProgressStatus;
use Ulams\Courses\Models\Topic;
use Ulams\Courses\Repositories\Contracts\CourseProgressRepositoryContract;

/**
 * Records the result of a run reported by the learner's browser (ADR 0170). The browser is not
 * trusted, so the score is recomputed here from the per-test results and the attempt is stored with
 * `verified = false`: it moves the topic's progress (the completion rule) and feeds the learner signal,
 * and nothing that issues certificates or checks compliance reads it as a grade.
 */
class AttemptService
{
    public function __construct(private readonly CourseProgressRepositoryContract $progress)
    {
    }

    /** The learner opened the exercise: IN_PROGRESS. */
    public function open(Topic $topic, Authenticatable $user): void
    {
        $this->ensureStarted($topic, $user);
    }

    /**
     * @param array<int, array{name: string, status: string, message?: ?string}> $results already validated
     * @param array{files_hash?: ?string, runner?: ?string, duration_ms?: ?int} $meta
     * @return array{attempt: CodeExerciseAttempt, status: int}
     */
    public function record(Topic $topic, CodeExerciseTopic $exercise, Authenticatable $user, array $results, array $meta = []): array
    {
        $results = $this->clean($results);
        $total = count($results);
        $passed = count(array_filter($results, fn (array $r) => $r['status'] === 'pass'));
        $score = $total === 0 ? 0 : (int) round($passed / $total * 100);
        $isPass = $total > 0 && $score >= $exercise->pass_score;
        $userId = (int) $user->getAuthIdentifier();

        $attempt = DB::transaction(function () use ($topic, $userId, $results, $total, $passed, $score, $meta) {
            // one writer per learner and topic, so attempt numbers stay gapless
            DB::table('users')->where('id', $userId)->lockForUpdate()->first();
            $next = (int) CodeExerciseAttempt::query()->where(['user_id' => $userId, 'topic_id' => $topic->getKey()])->max('attempt_no') + 1;

            return CodeExerciseAttempt::query()->create([
                'user_id' => $userId,
                'topic_id' => $topic->getKey(),
                'attempt_no' => $next,
                'mode' => 'practice',
                'verified' => false,
                'files_hash' => $meta['files_hash'] ?? null,
                'passed' => $passed,
                'total' => $total,
                'score' => $score,
                'results' => $results,
                'runner' => $meta['runner'] ?? null,
                'duration_ms' => $meta['duration_ms'] ?? null,
            ]);
        });

        $this->ensureStarted($topic, $user);
        if ($exercise->completion_rule === CompletionRule::ON_ATTEMPT->value || ($exercise->completion_rule === CompletionRule::ON_PASS->value && $isPass)) {
            $this->complete($topic, $user);
        }

        CodeAttemptFinished::dispatch($userId, (int) $topic->getKey(), (int) $attempt->getKey(), $attempt->attempt_no, $score, $isPass, false);

        return ['attempt' => $attempt, 'status' => $this->status($topic, $user)];
    }

    /** @return array{count: int, best_score: ?int, last: ?CodeExerciseAttempt} */
    public function summary(Topic $topic, Authenticatable $user): array
    {
        $query = CodeExerciseAttempt::query()->where(['user_id' => $user->getAuthIdentifier(), 'topic_id' => $topic->getKey()]);

        return [
            'count' => (clone $query)->count(),
            'best_score' => (clone $query)->max('score'),
            'last' => (clone $query)->orderByDesc('attempt_no')->first(),
        ];
    }

    /**
     * Short single-line names, short messages, no control characters.
     *
     * @param array<int, array<string, mixed>> $results
     * @return array<int, array{name: string, status: string, message: ?string}>
     */
    private function clean(array $results): array
    {
        $limits = (array) config('ulams_code_exercises.limits');
        $text = fn (mixed $v, int $max): string => mb_substr(trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $v)), 0, $max);

        return array_map(fn (array $r) => [
            'name' => $text($r['name'] ?? '', (int) $limits['max_test_name']),
            'status' => (string) $r['status'],
            'message' => isset($r['message']) && $r['message'] !== '' ? $text($r['message'], (int) $limits['max_message']) : null,
        ], array_values($results));
    }

    private function ensureStarted(Topic $topic, Authenticatable $user): void
    {
        if (!$topic->progress()->where('user_id', $user->getAuthIdentifier())->exists()) {
            $this->progress->updateInTopic($topic, $user, ProgressStatus::IN_PROGRESS);
        }
    }

    private function complete(Topic $topic, Authenticatable $user): void
    {
        $existing = $topic->progress()->where('user_id', $user->getAuthIdentifier())->first();
        if ($existing?->status !== ProgressStatus::COMPLETE) {
            $this->progress->updateInTopic($topic, $user, ProgressStatus::COMPLETE);
        }
    }

    private function status(Topic $topic, Authenticatable $user): int
    {
        return (int) ($topic->progress()->where('user_id', $user->getAuthIdentifier())->value('status') ?? ProgressStatus::INCOMPLETE);
    }
}
