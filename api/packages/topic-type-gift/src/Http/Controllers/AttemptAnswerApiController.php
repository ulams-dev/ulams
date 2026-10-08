<?php

namespace EscolaLms\TopicTypeGift\Http\Controllers;

use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\TopicTypeGift\Http\Requests\SaveAllAttemptAnswersRequest;
use EscolaLms\TopicTypeGift\Http\Requests\SaveAttemptAnswerRequest;
use EscolaLms\TopicTypeGift\Http\Resources\QuizAttemptResource;
use EscolaLms\TopicTypeGift\Services\Contracts\AttemptAnswerServiceContract;
use Illuminate\Http\JsonResponse;
use EscolaLms\TopicTypeGift\Http\Controllers\Swagger\AttemptAnswerApiSwagger;

class AttemptAnswerApiController extends EscolaLmsBaseController implements AttemptAnswerApiSwagger
{
    private AttemptAnswerServiceContract $answerService;

    public function __construct(AttemptAnswerServiceContract $answerService)
    {
        $this->answerService = $answerService;
    }

    public function saveAnswer(SaveAttemptAnswerRequest $request): JsonResponse
    {
        $this->answerService->saveAnswer($request->toDto());

        return $this->sendSuccess(__('Answer saved successfully.'));
    }

    public function saveAllAnswers(SaveAllAttemptAnswersRequest $request): JsonResponse
    {
        $this->answerService->saveAllAnswers($request->toDto());

        return $this->sendResponseForResource(new QuizAttemptResource($request->getAttempt()));
    }
}
