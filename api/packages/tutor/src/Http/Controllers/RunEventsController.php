<?php

namespace Ulams\Tutor\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Ulams\Core\Http\Sse\EventStream;
use Ulams\Tutor\Http\Controllers\Swagger\RunEventsSwagger;
use Ulams\Tutor\Models\Conversation;
use Ulams\Tutor\Models\Event;
use Ulams\Tutor\Streaming\RunLog;

/**
 * The AG-UI events of one streamed answer over SSE (ADR 0105), read with the shared loop in `core`. The run is
 * resolved through the conversation owned by the authenticated learner: a run of another learner, of another
 * tenant (its own database) or an unknown id is a 404. Closes after the final event or `tutor.stream.max_seconds`;
 * the client reconnects with the last id.
 */
class RunEventsController extends Controller implements RunEventsSwagger
{
    public function __construct(private readonly RunLog $runs)
    {
    }

    public function stream(Request $request, string $run): StreamedResponse
    {
        $conversationId = $this->runs->conversationOf($run);
        abort_if($conversationId === null, 404);
        Conversation::query()->where('user_id', (int) $request->user()->getKey())->findOrFail($conversationId);
        $this->runs->expireIfStale($run, $conversationId);

        return EventStream::response(
            fn (int $after) => Event::query()->where('run_id', $run)->where('id', '>', $after)->orderBy('id')->limit(1000)->get(),
            fn () => Cache::get(RunLog::cacheKey($run)),
            (int) ($request->header('Last-Event-ID') ?: $request->query('after', 0)),
            (int) config('tutor.stream.max_seconds'),
            (int) config('tutor.stream.poll_ms'),
            null,
            fn (Event $event) => in_array($event->type, RunLog::TERMINAL, true),
        );
    }
}
