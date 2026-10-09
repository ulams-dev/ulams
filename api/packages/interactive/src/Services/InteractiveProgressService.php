<?php

namespace Ulams\Interactive\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;
use Ulams\Courses\Enum\ProgressStatus;
use Ulams\Courses\Models\Topic;
use Ulams\Courses\Repositories\Contracts\CourseProgressRepositoryContract;
use Ulams\Interactive\Enums\CompletionRule;
use Ulams\Interactive\Models\InteractiveProgress;
use Ulams\Interactive\Models\InteractiveTopic;
use Ulams\Lrs\Models\Access;
use Ulams\Lrs\Services\Contracts\XapiStatementServiceContract;

/**
 * Turns the events a package reports through the learner's own session (lesson page, bridge, front
 * BFF) into progress (ADR 0086). Completion goes through CourseProgressRepositoryContract, once,
 * after an IN_PROGRESS row exists (ADR 0018). A package never holds a token.
 */
class InteractiveProgressService
{
    public function __construct(private readonly CourseProgressRepositoryContract $progress)
    {
    }

    /** The learner opened the topic: IN_PROGRESS, and complete right away for `on_open`. */
    public function open(Topic $topic, Authenticatable $user): int
    {
        $this->ensureStarted($topic, $user);
        $content = $this->content($topic);
        if ($content !== null && $content->completion_rule === CompletionRule::ON_OPEN->value) {
            $this->complete($topic, $user);
        }

        return $this->status($topic, $user);
    }

    /**
     * @param array<int, array<string, mixed>> $events validated bridge events (stepChanged, progress, complete, score, event)
     * @return array{status: int, progress: array<string, mixed>}
     */
    public function apply(Topic $topic, Authenticatable $user, array $events): array
    {
        $content = $this->content($topic);
        $version = $content?->resolveVersion();
        $steps = $version?->stepIds() ?? [];
        $rule = $content?->completion_rule ?? CompletionRule::ON_RANGE_END->value;

        $from = $content?->start_step !== null ? (int) array_search($content->start_step, $steps, true) : 0;
        $endStep = $content?->end_step ?? ($steps === [] ? null : $steps[array_key_last($steps)]);
        $to = $endStep !== null && in_array($endStep, $steps, true) ? (int) array_search($endStep, $steps, true) : max(0, count($steps) - 1);

        $this->ensureStarted($topic, $user);
        $done = false;
        $statements = [];

        $row = DB::transaction(function () use ($topic, $user, $events, $steps, $rule, $from, $to, $content, &$done, &$statements) {
            $row = InteractiveProgress::query()
                ->where(['topic_id' => $topic->getKey(), 'user_id' => $user->getAuthIdentifier()])
                ->lockForUpdate()
                ->first() ?? new InteractiveProgress(['topic_id' => $topic->getKey(), 'user_id' => $user->getAuthIdentifier()]);

            foreach ($events as $event) {
                switch ($event['type'] ?? null) {
                    case 'stepChanged':
                        $index = array_search($event['step'], $steps, true);
                        if ($index === false) {
                            break; // a step the manifest does not know: ignored
                        }
                        $row->last_step = $event['step'];
                        // a step outside [start_step, end_step] is recorded, but never completes the topic
                        if ($rule === CompletionRule::ON_RANGE_END->value && $index === $to && $index >= $from) {
                            $done = true;
                        }
                        break;
                    case 'progress':
                        $row->max_progress = max((float) $row->max_progress, round((float) $event['value'], 4));
                        break;
                    case 'complete':
                        if (in_array($rule, [CompletionRule::ON_RANGE_END->value, CompletionRule::ON_COMPLETE->value], true)) {
                            $done = true;
                        }
                        break;
                    case 'score':
                        $raw = (float) $event['raw'];
                        $max = (float) $event['max'];
                        if ($max <= 0) {
                            break;
                        }
                        $best = $row->score_max > 0 ? $row->score_raw / $row->score_max : -1.0;
                        if ($raw / $max > $best) {
                            $row->score_raw = $raw;
                            $row->score_max = $max;
                        }
                        if ($rule === CompletionRule::ON_SCORE->value && $content?->pass_score !== null && $raw / $max * 100 >= $content->pass_score) {
                            $done = true;
                        }
                        break;
                    case 'event':
                        $statements[] = $event;
                        break;
                }
            }
            if ($done && $row->completed_at === null) {
                $row->completed_at = Carbon::now();
            }
            $row->save();

            return $row;
        });

        if ($done) {
            $this->complete($topic, $user);
        }
        if ($statements !== []) {
            $this->storeStatements($topic, $user, $version?->package?->storage_key, $statements);
        }

        return ['status' => $this->status($topic, $user), 'progress' => [
            'last_step' => $row->last_step,
            'max_progress' => $row->max_progress,
            'score_raw' => $row->score_raw,
            'score_max' => $row->score_max,
            'completed' => $row->completed_at !== null,
        ]];
    }

    private function content(Topic $topic): ?InteractiveTopic
    {
        $content = $topic->topicable;

        return $content instanceof InteractiveTopic ? $content : null;
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

    /**
     * xAPI-like events are stored as xAPI statements when the tenant has an active LRS access, and
     * dropped otherwise. Best effort: a failure here never fails the progress call.
     *
     * @param array<int, array<string, mixed>> $events
     */
    private function storeStatements(Topic $topic, Authenticatable $user, ?string $packageKey, array $events): void
    {
        if (!class_exists(Access::class) || !app()->bound(XapiStatementServiceContract::class)) {
            return;
        }
        try {
            $access = Access::query()->where('active', true)->orderBy('id')->first();
            if ($access === null) {
                return;
            }
            $statements = array_map(fn (array $e) => [
                'id' => (string) Str::uuid(),
                'actor' => ['objectType' => 'Agent', 'account' => ['homePage' => rtrim((string) config('app.url'), '/'), 'name' => (string) $user->getAuthIdentifier()]],
                'verb' => ['id' => $e['verb']],
                'object' => ['objectType' => 'Activity', 'id' => sprintf('urn:ulams:interactive:%s:%s', $packageKey ?? 'unknown', $e['object'])],
                'result' => isset($e['result']) ? array_filter([
                    'response' => $e['result']['response'] ?? null,
                    'success' => $e['result']['success'] ?? null,
                    'score' => isset($e['result']['score']) ? ['raw' => $e['result']['score']] : null,
                ], fn ($v) => $v !== null) : null,
                'timestamp' => Carbon::now()->toIso8601String(),
            ], $events);
            $statements = array_map(fn (array $s) => array_filter($s, fn ($v) => $v !== null && $v !== []), $statements);
            app(XapiStatementServiceContract::class)->store($statements, $access);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
