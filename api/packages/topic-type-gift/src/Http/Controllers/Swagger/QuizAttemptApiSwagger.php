<?php

namespace EscolaLms\TopicTypeGift\Http\Controllers\Swagger;

use EscolaLms\TopicTypeGift\Http\Requests\EndQuizAttemptRequest;
use EscolaLms\TopicTypeGift\Http\Requests\GetActiveAttemptRequest;
use EscolaLms\TopicTypeGift\Http\Requests\ListQuizAttemptRequest;
use EscolaLms\TopicTypeGift\Http\Requests\ReadQuizAttemptRequest;
use EscolaLms\TopicTypeGift\Http\Requests\SaveAttemptAnswerRequest;
use Illuminate\Http\JsonResponse;

interface QuizAttemptApiSwagger
{
    /**
     * @OA\Get(
     *      path="/api/quiz-attempts",
     *      summary="Get my quiz attempts",
     *      tags={"Gift Quiz Attempt"},
     *      security={
     *          {"passport": {}},
     *      },
     *     @OA\Parameter(
     *          name="page",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="integer",
     *          ),
     *      ),
     *     @OA\Parameter(
     *          name="per_page",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="integer",
     *          ),
     *      ),
     *     @OA\Parameter(
     *          name="topic_gift_quiz_id",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="number",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successfull operation",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(
     *                  type="object",
     *                  @OA\Property(
     *                      property="success",
     *                      type="boolean"
     *                  ),
     *                  @OA\Property(
     *                      property="data",
     *                      type="array",
     *                      @OA\Items(@OA\Schema(ref="#/components/schemas/QuizAttemptSimpleResource"))
     *                  ),
     *                  @OA\Property(
     *                      property="message",
     *                      type="string"
     *                  )
     *              )
     *          )
     *      )
     * )
     */
    public function index(ListQuizAttemptRequest $request): JsonResponse;

    /**
     * @OA\Get(
     *     path="/api/quiz-attempts/{id}",
     *     summary="Get my quiz attempt by id",
     *      tags={"Gift Quiz Attempt"},
     *     security={
     *          {"passport": {}},
     *      },
     *     @OA\Parameter(
     *          name="id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="integer",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successfull operation",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(
     *                  type="object",
     *                  @OA\Property(
     *                      property="success",
     *                      type="boolean"
     *                  ),
     *                  @OA\Property(
     *                      property="data",
     *                      @OA\Schema(ref="#/components/schemas/QuizAttemptSimpleResource")
     *                  ),
     *                  @OA\Property(
     *                      property="message",
     *                      type="string"
     *                  )
     *              )
     *          )
     *      )
     * )
     */
    public function read(ReadQuizAttemptRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *      path="/api/quiz-attempts",
     *      summary="Create a new attempt or return an active attempt",
     *      tags={"Gift Quiz Attempt"},
     *      description="Store Gift Quiz Attempt",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(ref="#/components/schemas/GetActiveAttemptRequest")
     *          ),
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Successfull operation",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(
     *                  type="object",
     *                  @OA\Property(
     *                      property="success",
     *                      type="boolean"
     *                  ),
     *                  @OA\Property(
     *                      property="data",
     *                      @OA\Schema(ref="#/components/schemas/QuizAttemptResource")
     *                  ),
     *                  @OA\Property(
     *                      property="message",
     *                      type="string"
     *                  )
     *              )
     *          )
     *      )
     * )
     */
    public function getActiveAttempt(GetActiveAttemptRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *      path="/api/quiz-attempts/{id}/end",
     *      summary="Finish quiz attempt",
     *      tags={"Gift Quiz Attempt"},
     *      security={
     *          {"passport": {}},
     *      },
     *     @OA\Parameter(
     *          name="id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="integer",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successfull operation",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(
     *                  type="object",
     *                  @OA\Property(
     *                      property="success",
     *                      type="boolean"
     *                  ),
     *                  @OA\Property(
     *                      property="data",
     *                      @OA\Schema(ref="#/components/schemas/QuizAttemptResource")
     *                  ),
     *                  @OA\Property(
     *                      property="message",
     *                      type="string"
     *                  )
     *              )
     *          )
     *      )
     * )
     */
    public function markAsEnded(EndQuizAttemptRequest $request): JsonResponse;
}
