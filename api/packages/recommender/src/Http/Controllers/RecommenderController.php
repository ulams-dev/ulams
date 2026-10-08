<?php

namespace EscolaLms\Recommender\Http\Controllers;

use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\Recommender\Dto\MeetRecordingDto;
use EscolaLms\Recommender\Dto\MeetRecordingScreenDto;
use EscolaLms\Recommender\Http\Controllers\Swagger\RecommenderControllerSwagger;
use EscolaLms\Recommender\Http\Requests\AggregatedFrameRequest;
use EscolaLms\Recommender\Http\Requests\CourseRecommendationRequest;
use EscolaLms\Recommender\Http\Requests\MeetRecordingRequest;
use EscolaLms\Recommender\Http\Requests\MeetRecordingScreen;
use EscolaLms\Recommender\Http\Requests\TopicRecommendationRequest;
use EscolaLms\Recommender\Http\Resources\CourseRecommendationResource;
use EscolaLms\Recommender\Http\Resources\MeetRecordingResource;
use EscolaLms\Recommender\Http\Resources\TopicRecommendationResource;
use EscolaLms\Recommender\Services\Contracts\RecommenderServiceContract;
use EscolaLms\Recommender\Dto\AggregatedFrameDto;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class RecommenderController extends EscolaLmsBaseController implements RecommenderControllerSwagger
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
