<?php

namespace Ulams\Tutor\Streaming;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Ulams\Tutor\Models\Event;

/**
 * Appends AG-UI events of a streamed tutor answer to `tutor_events` (ADR 0105). The row id is the SSE event id.
 * After each append the run's "last event" key in the cache is bumped so open SSE connections wake without
 * querying the table on every tick (ADR 0029). A run is open from its RUN_STARTED row until it has a
 * RUN_FINISHED or RUN_ERROR row; one that outlives the job timeout (a worker that died) is closed with a
 * RUN_ERROR the next time somebody looks, so a conversation never stays busy.
 */
final class RunLog
{
    public const TERMINAL = ['RUN_FINISHED', 'RUN_ERROR'];

    /** Seconds on top of the job timeout before a run counts as abandoned (queue pickup, clock skew). */
    public const GRACE_SECONDS = 30;

    public static function cacheKey(string $runId): string
    {
        return "tutor:last_event:{$runId}";
    }

    public static function newRunId(): string
    {
        return strtolower((string) Str::ulid());
    }

    /** @param array<string,mixed> $payload */
    public function append(string $runId, string $conversationId, string $type, array $payload = []): Event
    {
        $event = Event::query()->create([
            'run_id' => $runId,
            'conversation_id' => $conversationId,
            'type' => $type,
            'payload' => ['type' => $type] + $payload + ['timestamp' => (int) floor(microtime(true) * 1000)],
            'created_at' => now(),
        ]);
        try {
            Cache::put(self::cacheKey($runId), $event->id, now()->addHour());
        } catch (\Throwable) {
            // the SSE endpoint falls back to polling the table
        }

        return $event;
    }

    public function started(string $runId, string $conversationId): Event
    {
        return $this->append($runId, $conversationId, 'RUN_STARTED', ['threadId' => $conversationId, 'runId' => $runId]);
    }

    /** @param array<string,mixed> $result */
    public function finished(string $runId, string $conversationId, array $result = []): void
    {
        $this->append($runId, $conversationId, 'RUN_FINISHED', ['threadId' => $conversationId, 'runId' => $runId] + ($result ? ['result' => $result] : []));
    }

    public function error(string $runId, string $conversationId, string $message, string $code = 'error'): void
    {
        if (!$this->isFinished($runId)) {
            $this->append($runId, $conversationId, 'RUN_ERROR', ['message' => $message, 'code' => $code, 'runId' => $runId]);
        }
    }

    public function isFinished(string $runId): bool
    {
        return Event::query()->where('run_id', $runId)->whereIn('type', self::TERMINAL)->exists();
    }

    /** The conversation a run belongs to, null for an unknown run. */
    public function conversationOf(string $runId): ?string
    {
        $id = Event::query()->where('run_id', $runId)->where('type', 'RUN_STARTED')->value('conversation_id');

        return $id !== null ? (string) $id : null;
    }

    /** The id of the answer that is still being written for this conversation, if any. */
    public function openRun(string $conversationId): ?string
    {
        $open = Event::query()->where('conversation_id', $conversationId)->where('type', 'RUN_STARTED')
            ->whereNotExists(fn ($q) => $q->from('tutor_events as done')->whereColumn('done.run_id', 'tutor_events.run_id')->whereIn('done.type', self::TERMINAL))
            ->orderBy('id')->get();
        $limit = now()->subSeconds((int) config('tutor.stream.timeout_seconds') + self::GRACE_SECONDS);
        $live = null;
        foreach ($open as $started) {
            if ($started->created_at < $limit) {
                $this->error($started->run_id, $conversationId, 'The tutor took too long to answer. Please ask again.', 'expired');
            } else {
                $live ??= $started->run_id;
            }
        }

        return $live;
    }

    /** Closes a run that outlived its timeout; the events endpoint calls this before it tails. */
    public function expireIfStale(string $runId, string $conversationId): void
    {
        $this->openRun($conversationId);
    }
}
