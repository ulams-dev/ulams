<?php

namespace Ulams\Jitsi\Http\Controllers;

use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Jitsi\Dto\RecordedVideoDto;
use Ulams\Jitsi\Http\Requests\RecordedVideoRequest;
use Ulams\Jitsi\Services\Contracts\JitsiVideoServiceContract;
use Illuminate\Http\JsonResponse;

class JitsiApiController extends UlamsBaseController
{
    public function __construct(
        private JitsiVideoServiceContract $jitsiVideoService,
    ) {}

    public function recordedVideo(RecordedVideoRequest $request): JsonResponse
    {
        $this->jitsiVideoService->recordedVideo(RecordedVideoDto::instantiateFromRequest($request));
        return $this->sendSuccess(__('Screen saved successfully'));
    }
}
