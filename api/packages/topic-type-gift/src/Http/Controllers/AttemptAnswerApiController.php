<?php

namespace Ulams\TopicTypeGift\Http\Controllers;

use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\TopicTypeGift\Http\Requests\SaveAllAttemptAnswersRequest;
use Ulams\TopicTypeGift\Http\Requests\SaveAttemptAnswerRequest;
use Ulams\TopicTypeGift\Http\Resources\QuizAttemptResource;
use Ulams\TopicTypeGift\Services\Contracts\AttemptAnswerServiceContract;
use Illuminate\Http\JsonResponse;
use Ulams\TopicTypeGift\Http\Controllers\Swagger\AttemptAnswerApiSwagger;

class AttemptAnswerApiController extends UlamsBaseController implements AttemptAnswerApiSwagger
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
