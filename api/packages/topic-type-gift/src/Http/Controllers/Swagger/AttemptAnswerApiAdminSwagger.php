<?php

namespace EscolaLms\TopicTypeGift\Http\Controllers\Swagger;

use EscolaLms\TopicTypeGift\Http\Requests\Admin\AdminUpdateAttemptAnswerRequest;
use Illuminate\Http\JsonResponse;

interface AttemptAnswerApiAdminSwagger
{
    /**
     * @OA\Patch(
     *      path="/api/admin/quiz-answers/{id}",
     *      summary="Update answer",
     *      tags={"Admin Gift Quiz Attempt"},
     *      description="Update answer",
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
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(ref="#/components/schemas/AdminUpdateAttemptAnswerRequest")
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
     *                      @OA\Schema(ref="#/components/schemas/AttemptAnswerResource")
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
    public function update(AdminUpdateAttemptAnswerRequest $request): JsonResponse;
}
