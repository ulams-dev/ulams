<?php

namespace EscolaLms\TopicTypeGift\Http\Controllers;

use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\TopicTypeGift\Http\Controllers\Swagger\GiftQuizApiAdminSwagger;
use EscolaLms\TopicTypeGift\Http\Requests\Admin\AdminListGiftQuizRequest;
use EscolaLms\TopicTypeGift\Http\Requests\Admin\AdminReadGiftQuizRequest;
use EscolaLms\TopicTypeGift\Http\Requests\Admin\AdminUpdateGiftQuizRequest;
use EscolaLms\TopicTypeGift\Http\Resources\AdminGiftQuizResource;
use EscolaLms\TopicTypeGift\Http\Resources\GiftQuizSimpleResource;
use EscolaLms\TopicTypeGift\Services\Contracts\GiftQuizServiceContract;
use Illuminate\Http\JsonResponse;

class GiftQuizApiAdminController extends EscolaLmsBaseController implements GiftQuizApiAdminSwagger
{
    private GiftQuizServiceContract $giftQuizService;

    public function __construct(GiftQuizServiceContract $giftQuizService)
    {
        $this->giftQuizService = $giftQuizService;
    }

    public function index(AdminListGiftQuizRequest $request): JsonResponse
    {
        $result = $this->giftQuizService->getQuizzesByCourse($request->getCourseId());

        return $this->sendResponseForResource(GiftQuizSimpleResource::collection($result));
    }

    public function read(AdminReadGiftQuizRequest $request): JsonResponse
    {
        return $this->sendResponseForResource(AdminGiftQuizResource::make($request->getGiftQuiz()), __('Gift Quiz retrieved successfully'));
    }

    public function update(AdminUpdateGiftQuizRequest $request): JsonResponse
    {
        $result = $this->giftQuizService->update($request->getId(), $request->toDto());

        return $this->sendResponseForResource(AdminGiftQuizResource::make($result), __('Gift Quiz updated successfully'));
    }
}
