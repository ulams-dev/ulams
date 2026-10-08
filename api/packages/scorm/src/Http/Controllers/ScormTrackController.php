<?php

namespace Ulams\Scorm\Http\Controllers;

use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Scorm\Http\Controllers\Swagger\ScormTrackControllerContract;
use Ulams\Scorm\Http\Requests\GetScormTrackRequest;
use Ulams\Scorm\Http\Requests\SetScormTrackRequest;
use Ulams\Scorm\Services\Contracts\ScormTrackServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScormTrackController extends UlamsBaseController implements ScormTrackControllerContract
{
    /** @var ScormTrackServiceContract */
    private ScormTrackServiceContract $scormTrackService;

    public function __construct(ScormTrackServiceContract $scormTrackService)
    {
        $this->scormTrackService = $scormTrackService;
    }

    public function set(SetScormTrackRequest $request, string $uuid): JsonResponse
    {
        $this->scormTrackService->updateScoTracking(
            $uuid,
            $request->user()->getKey(),
            $request->input('cmi')
        );

        return $this->sendSuccess();
    }

    public function get(GetScormTrackRequest $request, int $scoId, string $key): JsonResponse
    {
        $data = $this->scormTrackService->getUserResultSpecifiedValue($key, $scoId, $request->user()->getKey());
        return new JsonResponse($data);
    }
}
