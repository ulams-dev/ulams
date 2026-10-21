<?php

namespace Ulams\Core\Http\Sse;

use Closure;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The server side of an append-only event log read over SSE, shared by the Course Builder (ADR 0011) and
 * the course tutor (ADR 0105). The caller supplies how to read the rows after an id and, optionally, a cheap
 * "last event" marker (a cache value bumped on every append): the loop only queries the table when the
 * marker moved and falls back to polling it when there is none. Each row is sent with its id so a client
 * resumes with `Last-Event-ID`. The connection closes after `$maxSeconds` (so FPM workers are not held; the
 * client reconnects with the last id), when the client is gone, or right after a row the `$last` callback
 * calls final. A comment ping goes out every `$pingSeconds` to keep proxies from closing an idle stream.
 */
final class EventStream
{
    public const PING_SECONDS = 10;

    /**
     * @param Closure(int):iterable<object> $fetch rows with an int `id` and an array `payload`, after the given id, in id order
     * @param Closure():mixed|null $marker value that changes when rows were appended; null result means unknown
     * @param array<string,mixed>|Closure():array<string,mixed>|null $preamble sent first as an event without an id (not stored, not resumable)
     * @param Closure(object):bool|null $last true for a row after which the stream ends
     */
    public static function response(
        Closure $fetch,
        ?Closure $marker,
        int $after,
        int $maxSeconds,
        int $pollMs = 500,
        array|Closure|null $preamble = null,
        ?Closure $last = null,
        int $pingSeconds = self::PING_SECONDS,
    ): StreamedResponse {
        $pollMs = max(100, $pollMs);

        return response()->stream(function () use ($fetch, $marker, $after, $maxSeconds, $pollMs, $preamble, $last, $pingSeconds) {
            @ini_set('zlib.output_compression', '0');
            echo "retry: 1000\n\n";
            if ($preamble !== null) {
                echo 'data: ' . self::json($preamble instanceof Closure ? $preamble() : $preamble) . "\n\n";
            }
            self::flush();

            $deadline = microtime(true) + $maxSeconds;
            $lastPing = microtime(true);
            $seen = null;
            do {
                $mark = $marker !== null ? self::mark($marker) : null;
                if ($mark === null || $mark !== $seen) {
                    $seen = $mark;
                    $finished = false;
                    foreach ($fetch($after) as $row) {
                        echo "id: {$row->id}\n";
                        echo 'data: ' . self::json($row->payload) . "\n\n";
                        $after = (int) $row->id;
                        if ($last !== null && $last($row)) {
                            $finished = true;
                            break;
                        }
                    }
                    self::flush();
                    if ($finished) {
                        return;
                    }
                }
                if ($maxSeconds <= 0 || connection_aborted()) {
                    break;
                }
                if (microtime(true) - $lastPing > $pingSeconds) {
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

    /** @param Closure():mixed $marker */
    private static function mark(Closure $marker): mixed
    {
        try {
            return $marker();
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param array<string,mixed> $data */
    private static function json(array $data): string
    {
        return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function flush(): void
    {
        if (ob_get_level() > 0) {
            @ob_flush();
        }
        flush();
    }
}
