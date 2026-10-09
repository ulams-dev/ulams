<?php

namespace Ulams\Scorm\Http\Controllers\Swagger;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

interface ScormContentControllerContract
{
    /**
     * @OA\Post(
     *     path="/api/scorm/launch/{uuid}",
     *     summary="Start a SCO on the tenant content origin",
     *     description="Issues a SCO-scoped tracking token and returns the player URL on the content origin. `url` is null when no content origin is configured; use /api/scorm/play/{uuid} then.",
     *     tags={"SCORM"},
     *     security={{"passport": {}}},
     *     @OA\Parameter(name="uuid", in="path", required=true, description="SCO uuid", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Player URL (or null) and token expiry"),
     *     @OA\Response(response=401, description="endpoint requires authentication"),
     *     @OA\Response(response=404, description="unknown SCO")
     * )
     */
    public function launch(Request $request, string $uuid): JsonResponse;

    /**
     * @OA\Get(
     *     path="/api/scorm/content/{uuid}",
     *     summary="Launch data for the content-origin player",
     *     description="Authenticated with the tracking token from /api/scorm/launch/{uuid} in the X-Ulams-Tracking-Token header, not with a user token.",
     *     tags={"SCORM"},
     *     @OA\Parameter(name="uuid", in="path", required=true, description="SCO uuid", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="SCORM version, entry URL relative to the content origin, learner CMI"),
     *     @OA\Response(response=401, description="invalid, expired or foreign tracking token")
     * )
     */
    public function show(Request $request, string $uuid): JsonResponse;

    /**
     * @OA\Post(
     *     path="/api/scorm/content/{uuid}/track",
     *     summary="Store CMI data sent by the content-origin player",
     *     description="Authenticated with the tracking token in the X-Ulams-Tracking-Token header.",
     *     tags={"SCORM"},
     *     @OA\Parameter(name="uuid", in="path", required=true, description="SCO uuid", @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(type="object", @OA\Property(property="cmi", type="object"))),
     *     @OA\Response(response=200, description="stored"),
     *     @OA\Response(response=401, description="invalid, expired or foreign tracking token")
     * )
     */
    public function track(Request $request, string $uuid): JsonResponse;

    /**
     * @OA\Get(
     *     path="/api/scorm/assets/scorm-again.min.js",
     *     summary="Vendored scorm-again runtime (MIT) for the legacy player",
     *     tags={"SCORM"},
     *     @OA\Response(response=200, description="JavaScript")
     * )
     */
    public function scormAgain(): BinaryFileResponse;
}
