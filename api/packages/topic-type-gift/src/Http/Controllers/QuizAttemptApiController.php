<?php

namespace Ulams\TopicTypeGift\Http\Controllers;

use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\TopicTypeGift\Exceptions\TooManyAttemptsException;
use Ulams\TopicTypeGift\Http\Controllers\Swagger\QuizAttemptApiSwagger;
use Ulams\TopicTypeGift\Http\Requests\EndQuizAttemptRequest;
use Ulams\TopicTypeGift\Http\Requests\GetActiveAttemptRequest;
use Ulams\TopicTypeGift\Http\Requests\ListQuizAttemptRequest;
use Ulams\TopicTypeGift\Http\Requests\ReadQuizAttemptRequest;
use Ulams\TopicTypeGift\Http\Resources\QuizAttemptResource;
use Ulams\TopicTypeGift\Http\Resources\QuizAttemptSimpleResource;
use Ulams\TopicTypeGift\Jobs\MarkAttemptAsEnded;
use Ulams\TopicTypeGift\Services\Contracts\QuizAttemptServiceContract;
use Illuminate\Http\JsonResponse;

class QuizAttemptApiController extends UlamsBaseController implements QuizAttemptApiSwagger
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
