<?php

namespace Ulams\Video\Http\Controllers;

use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Video\Http\Controllers\Swagger\VideoControllerSwagger;
use Ulams\Video\Http\Requests\VideoProcessStateRequest;
use Ulams\Video\Http\Resources\VideoProcessStateResource;
use Ulams\Video\Repositories\Contracts\VideoRepositoryContract;
use Illuminate\Http\JsonResponse;

class VideoController extends UlamsBaseController implements VideoControllerSwagger
{
    private VideoRepositoryContract $videoRepository;

    public function __construct(VideoRepositoryContract $videoRepository)
    {
        $this->videoRepository = $videoRepository;
    }

    public function states(VideoProcessStateRequest $request): JsonResponse
    {
        return $this->sendResponseForResource(
            VideoProcessStateResource::collection($this->videoRepository->searchByCriteria($request->criteria())),
            __('Video states retrieved successfully')
        );
    }
}
