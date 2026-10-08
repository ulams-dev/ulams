<?php

namespace EscolaLms\TopicTypeGift\Http\Controllers\Swagger;

use EscolaLms\TopicTypeGift\Http\Requests\SaveAllAttemptAnswersRequest;
use EscolaLms\TopicTypeGift\Http\Requests\SaveAttemptAnswerRequest;
use Illuminate\Http\JsonResponse;

interface AttemptAnswerApiSwagger
{

    /**
     * @OA\Post(
     *      path="/api/quiz-answers",
     *      summary="Save your answer",
     *      tags={"Gift Quiz Attempt"},
     *      description="Save your answer",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(ref="#/components/schemas/SaveAttemptAnswerRequest")
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
     *                      property="message",
     *                      type="string"
     *                  )
     *              )
     *          )
     *      )
     * )
     */
    public function saveAnswer(SaveAttemptAnswerRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *      path="/api/quiz-answers/all",
     *      summary="Save your all answers",
     *      tags={"Gift Quiz Attempt"},
     *      description="Save your all answers",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(ref="#/components/schemas/SaveAllAttemptAnswerRequest")
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
    public function saveAllAnswers(SaveAllAttemptAnswersRequest $request): JsonResponse;
}
