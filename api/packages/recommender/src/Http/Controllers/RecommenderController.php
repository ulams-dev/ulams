<?php

namespace Ulams\Recommender\Http\Controllers;

use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Recommender\Dto\MeetRecordingDto;
use Ulams\Recommender\Dto\MeetRecordingScreenDto;
use Ulams\Recommender\Http\Controllers\Swagger\RecommenderControllerSwagger;
use Ulams\Recommender\Http\Requests\AggregatedFrameRequest;
use Ulams\Recommender\Http\Requests\CourseRecommendationRequest;
use Ulams\Recommender\Http\Requests\MeetRecordingRequest;
use Ulams\Recommender\Http\Requests\MeetRecordingScreen;
use Ulams\Recommender\Http\Requests\TopicRecommendationRequest;
use Ulams\Recommender\Http\Resources\CourseRecommendationResource;
use Ulams\Recommender\Http\Resources\MeetRecordingResource;
use Ulams\Recommender\Http\Resources\TopicRecommendationResource;
use Ulams\Recommender\Services\Contracts\RecommenderServiceContract;
use Ulams\Recommender\Dto\AggregatedFrameDto;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class RecommenderController extends UlamsBaseController implements RecommenderControllerSwagger
{
    private RecommenderServiceContract $recommenderService;

    public function __construct(RecommenderServiceContract $recommenderService)
    {
        $this->recommenderService = $recommenderService;
    }

    public function course(CourseRecommendationRequest $request, int $courseId): JsonResponse
    {
        $result = $this->recommenderService->completionOfCourse($courseId);

        return $this->sendResponseForResource(
            CourseRecommendationResource::make($result),
            __('Course recommendation retrieved successfully')
        );
    }

    public function topic(TopicRecommendationRequest $request, int $lessonId): JsonResponse
    {
        $result = $this->recommenderService->matchTopicType($lessonId);

        return $this->sendResponseForResource(
            TopicRecommendationResource::make($result),
            __('Topic recommendation retrieved successfully')
        );
    }

    public function aggregateFrameSave(AggregatedFrameRequest $request): \Illuminate\Http\Response
    {
        $dto = new AggregatedFrameDto($request->all());
        $this->recommenderService->aggregatedFrameSave($dto);

        return Response::noContent();
    }

    public function meetRecordings(MeetRecordingRequest $request): JsonResponse
    {
        Log::info('[POST] /api/recommender/meet-recordings with', ['request_data' => $request->all()]);
        $dto = new MeetRecordingDto($request->all());
        $model = $this->recommenderService->meetRecording($dto);

        return $this->sendResponseForResource(
            MeetRecordingResource::make($model), __('Meet recording saved successfully')
        );
    }

    public function meetRecordingScreen(MeetRecordingScreen $request): JsonResponse
    {
        $dto = new MeetRecordingScreenDto($request->all());
        $this->recommenderService->meetRecordingScreen($dto);

        return $this->sendResponse(__('Meet recording screens saved successfully'));
    }
}
