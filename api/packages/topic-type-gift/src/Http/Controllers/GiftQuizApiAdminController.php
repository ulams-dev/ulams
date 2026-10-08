<?php

namespace Ulams\TopicTypeGift\Http\Controllers;

use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\TopicTypeGift\Http\Controllers\Swagger\GiftQuizApiAdminSwagger;
use Ulams\TopicTypeGift\Http\Requests\Admin\AdminListGiftQuizRequest;
use Ulams\TopicTypeGift\Http\Requests\Admin\AdminReadGiftQuizRequest;
use Ulams\TopicTypeGift\Http\Requests\Admin\AdminUpdateGiftQuizRequest;
use Ulams\TopicTypeGift\Http\Resources\AdminGiftQuizResource;
use Ulams\TopicTypeGift\Http\Resources\GiftQuizSimpleResource;
use Ulams\TopicTypeGift\Services\Contracts\GiftQuizServiceContract;
use Illuminate\Http\JsonResponse;

class GiftQuizApiAdminController extends UlamsBaseController implements GiftQuizApiAdminSwagger
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
