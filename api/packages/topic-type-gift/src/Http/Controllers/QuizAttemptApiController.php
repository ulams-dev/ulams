<?php

namespace EscolaLms\TopicTypeGift\Http\Controllers;

use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\TopicTypeGift\Exceptions\TooManyAttemptsException;
use EscolaLms\TopicTypeGift\Http\Controllers\Swagger\QuizAttemptApiSwagger;
use EscolaLms\TopicTypeGift\Http\Requests\EndQuizAttemptRequest;
use EscolaLms\TopicTypeGift\Http\Requests\GetActiveAttemptRequest;
use EscolaLms\TopicTypeGift\Http\Requests\ListQuizAttemptRequest;
use EscolaLms\TopicTypeGift\Http\Requests\ReadQuizAttemptRequest;
use EscolaLms\TopicTypeGift\Http\Resources\QuizAttemptResource;
use EscolaLms\TopicTypeGift\Http\Resources\QuizAttemptSimpleResource;
use EscolaLms\TopicTypeGift\Jobs\MarkAttemptAsEnded;
use EscolaLms\TopicTypeGift\Services\Contracts\QuizAttemptServiceContract;
use Illuminate\Http\JsonResponse;

class QuizAttemptApiController extends EscolaLmsBaseController implements QuizAttemptApiSwagger
{
    private QuizAttemptServiceContract $attemptService;

    public function __construct(QuizAttemptServiceContract $attemptService)
    {
        $this->attemptService = $attemptService;
    }

    public function index(ListQuizAttemptRequest $request): JsonResponse
    {
        $result = $this->attemptService->findByUser($request->getCriteriaDto(), $request->getPageDto(), auth()->id());

        return $this->sendResponseForResource(QuizAttemptSimpleResource::collection($result));
    }

    public function read(ReadQuizAttemptRequest $request): JsonResponse
    {
        return $this->sendResponseForResource(QuizAttemptResource::make($request->getAttempt()));
    }

    public function getActiveAttempt(GetActiveAttemptRequest $request): JsonResponse
    {
        try {
            $result = $this->attemptService->getActive($request->getQuizAttemptDto());
            return $this->sendResponseForResource(QuizAttemptResource::make($result), __('Quiz attempt created successfully.'));
        } catch (TooManyAttemptsException $e) {
            return $this->sendError($e->getMessage(), $e->getCode());
        }
    }

    public function markAsEnded(EndQuizAttemptRequest $request): JsonResponse
    {
        MarkAttemptAsEnded::dispatchSync($request->getId());
        $result = $this->attemptService->findById($request->getId());

        return $this->sendResponseForResource(QuizAttemptResource::make($result));
    }
}
