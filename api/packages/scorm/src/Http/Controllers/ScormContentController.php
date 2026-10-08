<?php

namespace Ulams\Scorm\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Scorm\Http\Controllers\Swagger\ScormContentControllerContract;
use Ulams\Scorm\Services\Contracts\ScormContentServiceContract;
use Ulams\Scorm\Services\TrackingToken;

class ScormContentController extends UlamsBaseController implements ScormContentControllerContract
{
    /**
     * Not `Authorization: Bearer`: Passport's guard (run by global middleware) blanks any bearer
     * header that is not one of its tokens, and the tracking token must never be mistaken for one.
     */
    public const TOKEN_HEADER = 'X-Ulams-Tracking-Token';

    public function __construct(private readonly ScormContentServiceContract $contentService)
    {
    }

    public function launch(Request $request, string $uuid): JsonResponse
    {
        // Any signed-in learner may play and be tracked (students do not hold scorm_track-update,
        // which the legacy /api/scorm/track endpoint requires; see docs/plans/phase-1.md decisions).
        $user = $request->user();
        $launch = $this->contentService->launch($uuid, (int) $user->getKey(), $request->getSchemeAndHttpHost());

        return $this->sendResponse(
            $launch ?? ['url' => null, 'origin' => null, 'expires_at' => null],
            $launch ? 'Content-origin player ready' : 'No content origin configured; use the legacy player'
        );
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $userId = TrackingToken::verify($request->header(self::TOKEN_HEADER), $uuid);
        if ($userId === null) {
            return $this->sendError('Invalid or expired tracking token', 401);
        }

        return $this->sendResponse($this->contentService->launchData($uuid, $userId), 'Launch data');
    }

    public function track(Request $request, string $uuid): JsonResponse
    {
        $userId = TrackingToken::verify($request->header(self::TOKEN_HEADER), $uuid);
        if ($userId === null) {
            return $this->sendError('Invalid or expired tracking token', 401);
        }

        $this->contentService->track($uuid, $userId, $request->input('cmi'));

        return $this->sendSuccess();
    }

    public function scormAgain(): BinaryFileResponse
    {
        return response()->file(__DIR__ . '/../../../resources/js/vendor/scorm-again/scorm-again.min.js', [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
