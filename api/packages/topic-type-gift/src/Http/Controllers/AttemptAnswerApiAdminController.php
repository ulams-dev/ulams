<?php

namespace Ulams\TopicTypeGift\Http\Controllers;

use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\TopicTypeGift\Http\Controllers\Swagger\AttemptAnswerApiAdminSwagger;
use Ulams\TopicTypeGift\Http\Requests\Admin\AdminUpdateAttemptAnswerRequest;
use Ulams\TopicTypeGift\Http\Resources\AttemptAnswerResource;
use Ulams\TopicTypeGift\Services\Contracts\AttemptAnswerServiceContract;
use Illuminate\Http\JsonResponse;

class AttemptAnswerApiAdminController extends UlamsBaseController implements AttemptAnswerApiAdminSwagger
{
    private AttemptAnswerServiceContract $answerService;

    public function __construct(AttemptAnswerServiceContract $answerService)
    {
        $this->answerService = $answerService;
    }

    public function update(AdminUpdateAttemptAnswerRequest $request): JsonResponse
    {
        $result = $this->answerService->adminUpdate($request->getId(), $request->toDto());

        return $this->sendResponseForResource(AttemptAnswerResource::make($result), __('Updated successfully'));
    }
}
