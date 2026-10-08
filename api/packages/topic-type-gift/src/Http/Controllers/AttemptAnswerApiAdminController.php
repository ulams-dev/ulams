<?php

namespace EscolaLms\TopicTypeGift\Http\Controllers;

use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\TopicTypeGift\Http\Controllers\Swagger\AttemptAnswerApiAdminSwagger;
use EscolaLms\TopicTypeGift\Http\Requests\Admin\AdminUpdateAttemptAnswerRequest;
use EscolaLms\TopicTypeGift\Http\Resources\AttemptAnswerResource;
use EscolaLms\TopicTypeGift\Services\Contracts\AttemptAnswerServiceContract;
use Illuminate\Http\JsonResponse;

class AttemptAnswerApiAdminController extends EscolaLmsBaseController implements AttemptAnswerApiAdminSwagger
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
