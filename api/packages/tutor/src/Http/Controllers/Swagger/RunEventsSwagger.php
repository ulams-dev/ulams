<?php

namespace Ulams\Tutor\Http\Controllers\Swagger;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface RunEventsSwagger
{
    /**
     * @OA\Get(
     *   path="/api/tutor/runs/{run}/events", operationId="tutor.runs.by-run.events.get",
     *   summary="AG-UI event stream (SSE) of one streamed tutor answer",
     *   description="Text events are a draft; the `CUSTOM` event `tutor.final` carries the validated answer with its citations and always replaces it. During an open quiz attempt only `tutor.final` is sent. The stream ends after `RUN_FINISHED` or `RUN_ERROR`, or after 25 seconds (reconnect with `Last-Event-ID`).",
     *   tags={"Tutor"},
     *   security={{"passport": {}}},
     *   @OA\Parameter(name="run", in="path", required=true, @OA\Schema(type="string")),
     *   @OA\Parameter(name="after", in="query", description="resume after this event id (or Last-Event-ID header)", @OA\Schema(type="integer")),
     *   @OA\Response(response=200, description="text/event-stream"),
     *   @OA\Response(response=404, description="Unknown run, or a run of another learner")
     * )
     */
    public function stream(Request $request, string $run): StreamedResponse;
}
