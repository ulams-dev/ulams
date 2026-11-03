<?php

namespace Ulams\CourseBuilder\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Ulams\Core\Http\Sse\EventStream;
use Ulams\CourseBuilder\Events\EventLog;
use Ulams\CourseBuilder\Http\Controllers\Concerns\ResolvesSessions;
use Ulams\CourseBuilder\Models\Event;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Services\SessionState;

/**
 * AG-UI events over SSE (ADR 0011). On connect: a STATE_SNAPSHOT (not stored, no id), then every
 * stored event after `Last-Event-ID` (or `?after=`), then new events as they are appended. The
 * connection closes after `course_builder.sse.max_seconds` (25 s) so PHP-FPM workers are not held;
 * the client reconnects with the last id. Between polls the loop watches the session's "last event"
 * key in the cache and only queries the table when it moved (polling fallback every 500 ms).
 *
 * @OA\Get(path="/api/admin/course-builder/sessions/{session}/events", operationId="admin.course-builder.sessions.by-session.events.get", summary="AG-UI event stream (SSE)", tags={"Admin Course Builder"}, security={{"passport": {}}},
 *     @OA\Parameter(name="session", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Parameter(name="after", in="query", description="resume after this event id (or Last-Event-ID header)", @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="text/event-stream"))
 */
class EventStreamController extends Controller
{
    use ResolvesSessions;

    public function stream(Request $request, string $session): StreamedResponse
    {
        $s = $this->sessionFor($request, $session);
        $after = (int) ($request->header('Last-Event-ID') ?: $request->query('after', 0));
        $max = (int) config('course_builder.sse.max_seconds', 25);
        $pollMs = max(100, (int) config('course_builder.sse.poll_ms', 500));

        return EventStream::response(
            fn (int $from) => self::events($s, $from),
            fn () => Cache::get(EventLog::cacheKey($s->id)),
            $after,
            $max,
            $pollMs,
            fn () => ['type' => 'STATE_SNAPSHOT', 'snapshot' => SessionState::snapshot($s)],
        );
    }

    /** @return iterable<Event> */
    private static function events(Session $s, int $after): iterable
    {
        return Event::query()->where('session_id', $s->id)->where('id', '>', $after)->orderBy('id')->limit(1000)->get();
    }
}
