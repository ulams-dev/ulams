<?php

namespace Ulams\CourseBuilder\Events;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Ulams\CourseBuilder\Models\Event;
use Ulams\CourseBuilder\Models\Run;
use Ulams\CourseBuilder\Models\Session;

/**
 * Appends AG-UI events to `course_builder_events` (ADR 0011). The row id is the SSE event id.
 * After each append the session's "last event" key in the cache is bumped so open SSE connections
 * wake without querying the table on every tick.
 *
 * A2UI v0.9 surfaces travel as `ACTIVITY_SNAPSHOT` events with activity type `a2ui-surface`;
 * `@ag-ui/core` 1.0.2 defines no A2UI convention, and activity events carry exactly this kind of
 * structured, replaceable UI state. The adapter on each side is this class and `@ulams/sdk`.
 */
final class EventLog
{
    public const A2UI_ACTIVITY = 'a2ui-surface';

    public static function cacheKey(string $sessionId): string
    {
        return "course_builder:last_event:{$sessionId}";
    }

    /** @param array<string,mixed> $payload */
    public function append(Session $session, ?Run $run, string $type, array $payload = []): Event
    {
        $event = Event::query()->create([
            'session_id' => $session->id,
            'run_id' => $run?->id,
            'type' => $type,
            'payload' => ['type' => $type] + $payload + ['timestamp' => (int) floor(microtime(true) * 1000)],
        ]);
        try {
            Cache::put(self::cacheKey($session->id), $event->id, now()->addHour());
        } catch (\Throwable) {
            // the SSE endpoint falls back to polling the table
        }

        return $event;
    }

    public function runStarted(Run $run): void
    {
        $this->append($run->session, $run, 'RUN_STARTED', ['threadId' => $run->session_id, 'runId' => $run->id]);
    }

    public function runFinished(Run $run, array $result = []): void
    {
        $this->append($run->session, $run, 'RUN_FINISHED', ['threadId' => $run->session_id, 'runId' => $run->id] + ($result ? ['result' => $result] : []));
    }

    public function runError(Run $run, string $message, string $code = 'error'): void
    {
        $this->append($run->session, $run, 'RUN_ERROR', ['message' => $message, 'code' => $code, 'runId' => $run->id]);
    }

    public function stepStarted(Run $run, string $name): void
    {
        $this->append($run->session, $run, 'STEP_STARTED', ['stepName' => $name]);
    }

    public function stepFinished(Run $run, string $name): void
    {
        $this->append($run->session, $run, 'STEP_FINISHED', ['stepName' => $name]);
    }

    /** Short assistant prose (never course content), as one start/content/end triple. */
    public function text(Session $session, ?Run $run, string $text): string
    {
        $id = 'msg_' . strtolower((string) Str::ulid());
        $this->append($session, $run, 'TEXT_MESSAGE_START', ['messageId' => $id, 'role' => 'assistant']);
        $this->append($session, $run, 'TEXT_MESSAGE_CONTENT', ['messageId' => $id, 'delta' => $text]);
        $this->append($session, $run, 'TEXT_MESSAGE_END', ['messageId' => $id]);

        return $id;
    }

    /** The author's own message, echoed so a reloaded thread shows it. */
    public function userText(Session $session, ?Run $run, string $text): void
    {
        $id = 'msg_' . strtolower((string) Str::ulid());
        $this->append($session, $run, 'TEXT_MESSAGE_START', ['messageId' => $id, 'role' => 'user']);
        $this->append($session, $run, 'TEXT_MESSAGE_CONTENT', ['messageId' => $id, 'delta' => $text]);
        $this->append($session, $run, 'TEXT_MESSAGE_END', ['messageId' => $id]);
    }

    /** @param array<int,array{op:string,path:string,value?:mixed}> $ops RFC 6902 */
    public function stateDelta(Session $session, ?Run $run, array $ops): void
    {
        if ($ops !== []) {
            $this->append($session, $run, 'STATE_DELTA', ['delta' => $ops]);
        }
    }

    /**
     * Streams (or replaces) an A2UI surface.
     *
     * @param array<int,array<string,mixed>> $components flat A2UI component list with a `root`
     * @param array<string,mixed> $data initial data model
     */
    public function surface(Session $session, ?Run $run, string $surfaceId, string $catalogId, array $components, array $data = [], array $meta = []): void
    {
        $messages = [
            ['version' => 'v0.9', 'createSurface' => ['surfaceId' => $surfaceId, 'catalogId' => $catalogId]],
            ['version' => 'v0.9', 'updateComponents' => ['surfaceId' => $surfaceId, 'components' => $components]],
        ];
        if ($data !== []) {
            $messages[] = ['version' => 'v0.9', 'updateDataModel' => ['surfaceId' => $surfaceId, 'path' => '/', 'value' => $data]];
        }
        $this->append($session, $run, 'ACTIVITY_SNAPSHOT', [
            'messageId' => $surfaceId,
            'activityType' => self::A2UI_ACTIVITY,
            'content' => ['surfaceId' => $surfaceId, 'messages' => $messages] + $meta,
            'replace' => true,
        ]);
    }

    public function deleteSurface(Session $session, ?Run $run, string $surfaceId): void
    {
        $this->append($session, $run, 'ACTIVITY_SNAPSHOT', [
            'messageId' => $surfaceId,
            'activityType' => self::A2UI_ACTIVITY,
            'content' => ['surfaceId' => $surfaceId, 'messages' => [['version' => 'v0.9', 'deleteSurface' => ['surfaceId' => $surfaceId]]]],
            'replace' => true,
        ]);
    }

    /** @param array<string,mixed> $value */
    public function custom(Session $session, ?Run $run, string $name, array $value): void
    {
        $this->append($session, $run, 'CUSTOM', ['name' => $name, 'value' => $value]);
    }
}
