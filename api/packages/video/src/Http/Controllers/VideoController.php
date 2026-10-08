<?php

namespace EscolaLms\Video\Http\Controllers;

use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\Video\Http\Controllers\Swagger\VideoControllerSwagger;
use EscolaLms\Video\Http\Requests\VideoProcessStateRequest;
use EscolaLms\Video\Http\Resources\VideoProcessStateResource;
use EscolaLms\Video\Repositories\Contracts\VideoRepositoryContract;
use Illuminate\Http\JsonResponse;

class VideoController extends EscolaLmsBaseController implements VideoControllerSwagger
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
