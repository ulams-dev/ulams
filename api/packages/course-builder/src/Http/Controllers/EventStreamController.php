<?php

namespace Ulams\CourseBuilder\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;
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
 * @OA\Get(path="/api/admin/course-builder/sessions/{id}/events", summary="AG-UI event stream (SSE)", tags={"Admin Course Builder"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
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

        return response()->stream(function () use ($s, $after, $max, $pollMs) {
            @ini_set('zlib.output_compression', '0');
            echo "retry: 1000\n\n";
            echo 'data: ' . json_encode(['type' => 'STATE_SNAPSHOT', 'snapshot' => SessionState::snapshot($s)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
            self::flush();

            $deadline = microtime(true) + $max;
            $lastPing = microtime(true);
            $seen = null;
            do {
                $marker = self::marker($s);
                if ($marker === null || $marker !== $seen) {
                    $seen = $marker;
                    foreach (self::events($s, $after) as $event) {
                        echo "id: {$event->id}\n";
                        echo 'data: ' . json_encode($event->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
                        $after = $event->id;
                    }
                    self::flush();
                }
                if ($max <= 0 || connection_aborted()) {
                    break;
                }
                if (microtime(true) - $lastPing > 10) {
                    echo ": ping\n\n";
                    self::flush();
                    $lastPing = microtime(true);
                }
                usleep($pollMs * 1000);
            } while (microtime(true) < $deadline);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    /** @return iterable<Event> */
    private static function events(Session $s, int $after): iterable
    {
        return Event::query()->where('session_id', $s->id)->where('id', '>', $after)->orderBy('id')->limit(1000)->get();
    }

    private static function marker(Session $s): mixed
    {
        try {
            return Cache::get(EventLog::cacheKey($s->id));
        } catch (\Throwable) {
            return null;
        }
    }

    private static function flush(): void
    {
        if (ob_get_level() > 0) {
            @ob_flush();
        }
        flush();
    }
}
