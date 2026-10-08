<?php

namespace EscolaLms\TopicTypeGift\Http\Controllers\Swagger;

use EscolaLms\TopicTypeGift\Http\Requests\Admin\AdminExportQuizResultsRequest;
use EscolaLms\TopicTypeGift\Http\Requests\Admin\AdminListQuizAttemptRequest;
use EscolaLms\TopicTypeGift\Http\Requests\Admin\AdminReadQuizAttemptRequest;
use EscolaLms\TopicTypeGift\Http\Requests\Admin\AdminUpdateQuizAttemptFeedbackRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

interface QuizAttemptApiAdminSwagger
{
    /**
     * @OA\Get(
     *      path="/api/admin/quiz-attempts",
     *      summary="Get all quiz attempts",
     *      tags={"Admin Gift Quiz Attempt"},
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
     *     @OA\Parameter(
     *          name="course_id",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="number",
     *          ),
     *      ),
     *     @OA\Parameter(
     *          name="user_id",
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
    public function index(AdminListQuizAttemptRequest $request): JsonResponse;

    /**
     * @OA\Get(
     *     path="/api/admin/quiz-attempts/{id}",
     *     summary="Get quiz attempt by id",
     *      tags={"Admin Gift Quiz Attempt"},
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
    public function read(AdminReadQuizAttemptRequest $request): JsonResponse;

    /**
     * @OA\Patch(
     *      path="/api/admin/quiz-attempts/{id}/feedback",
     *      summary="Add, edit or remove tutor feedback for a quiz attempt",
     *      tags={"Admin Gift Quiz Attempt"},
     *      description="Sets the tutor feedback (light rich text or video link) on a quiz attempt. Sending null or an empty string removes it.",
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
     *          required=false,
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(ref="#/components/schemas/AdminUpdateQuizAttemptFeedbackRequest")
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
    public function feedback(AdminUpdateQuizAttemptFeedbackRequest $request): JsonResponse;

    /**
     * @OA\Get(
     *      path="/api/admin/quiz-attempts/export",
     *      summary="Export course quiz results to an XLSX or XLS file",
     *      tags={"Admin Gift Quiz Attempt"},
     *      description="Exports all attempts of all students. Without topic_gift_quiz_id every quiz of the course is exported to a separate worksheet; with it a single worksheet is exported. The format query parameter selects the spreadsheet format (xlsx or xls), defaulting to xlsx.",
     *      security={
     *          {"passport": {}},
     *      },
     *     @OA\Parameter(
     *          name="course_id",
     *          required=true,
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
     *              type="integer",
     *          ),
     *      ),
     *     @OA\Parameter(
     *          name="format",
     *          required=false,
     *          in="query",
     *          description="Output spreadsheet format. Defaults to xlsx.",
     *          @OA\Schema(
     *              type="string",
     *              enum={"xlsx", "xls"},
     *              default="xlsx",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successfull operation",
     *          @OA\MediaType(
     *              mediaType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
     *          ),
     *          @OA\MediaType(
     *              mediaType="application/vnd.ms-excel"
     *          )
     *      )
     * )
     */
    public function export(AdminExportQuizResultsRequest $request): BinaryFileResponse;
}
