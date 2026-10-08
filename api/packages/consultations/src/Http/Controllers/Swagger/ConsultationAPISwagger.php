<?php

namespace EscolaLms\Consultations\Http\Controllers\Swagger;

use EscolaLms\Consultations\Http\Requests\ConsultationUserTermRequest;
use EscolaLms\Consultations\Http\Requests\ConsultationScreenSaveRequest;
use EscolaLms\Consultations\Http\Requests\FinishTermRequest;
use EscolaLms\Consultations\Http\Requests\GenerateSignedScreenUrlsRequest;
use EscolaLms\Consultations\Http\Requests\ListAPIConsultationsRequest;
use EscolaLms\Consultations\Http\Requests\ListConsultationsRequest;
use EscolaLms\Consultations\Http\Requests\ReportTermConsultationRequest;
use EscolaLms\Consultations\Http\Requests\ScheduleConsultationAPIRequest;
use EscolaLms\Consultations\Http\Requests\ShowAPIConsultationRequest;
use Illuminate\Http\JsonResponse;

interface ConsultationAPISwagger
{
    /**
     * @OA\Post(
     *      path="/api/consultations/report-term/{orderItemId}",
     *      security={
     *          {"passport": {}},
     *      },
     *      summary="Report term with bought consultation",
     *      tags={"Consultations"},
     *      description="Report term consultation",
     *      @OA\Parameter(
     *          name="orderItemId",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="integer",
     *          ),
     *      ),
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(
     *                  property="term",
     *                  type="string",
     *                  example="2022-10-12 10:45",
     *              ),
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *          ),
     *          @OA\Schema(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Bad request",
     *          @OA\MediaType(
     *              mediaType="application/json"
     *          )
     *      )
     * )
     */
    public function reportTerm(int $orderItemId, ReportTermConsultationRequest $request): JsonResponse;

    /**
     * @OA\Get(
     *      tags={"Consultations"},
     *      security={
     *          {"passport": {}},
     *      },
     *      path="/api/consultations/approve-term/{consultationTermId}",
     *      description="Approve reported term with consultation author",
     *      @OA\Parameter(
     *          name="consultationTermId",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="integer",
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="term",
     *          required=true,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *              example="2024-10-31 10:45"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="user_id",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="int"
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\MediaType(
     *              mediaType="application/json"
     *          ),
     *          @OA\Schema(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  type="array",
     *                  @OA\Items(ref="#/components/schemas/ConsultationTerm")
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Bad request",
     *          @OA\MediaType(
     *              mediaType="application/json"
     *          )
     *      )
     *   )
     */
    public function approveTerm(ConsultationUserTermRequest $request, int $consultationTermId): JsonResponse;

    /**
     * @OA\Get(
     *      tags={"Consultations"},
     *      path="/api/consultations/reject-term/{consultationTermId}",
     *      security={
     *          {"passport": {}},
     *      },
     *      description="Reject reported term with consultation author",
     *      @OA\Parameter(
     *          name="consultationTermId",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="integer",
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="term",
     *          required=true,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *              example="2024-10-31 10:45"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="user_id",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="int"
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\MediaType(
     *              mediaType="application/json"
     *          ),
     *          @OA\Schema(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  type="array",
     *                  @OA\Items(ref="#/components/schemas/ConsultationTerm")
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Bad request",
     *          @OA\MediaType(
     *              mediaType="application/json"
     *          )
     *      )
     *   )
     */
    public function rejectTerm(ConsultationUserTermRequest $request, int $consultationTermId): JsonResponse;

    /**
     * @OA\Get(
     *      tags={"Consultations"},
     *      path="/api/consultations/generate-jitsi/{consultationTermId}",
     *      security={
     *          {"passport": {}},
     *      },
     *      description="Generate jitsi object for approved consultation term",
     *      @OA\Parameter(
     *          name="consultationTermId",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="integer",
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="term",
     *          required=true,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *              example="2024-10-31 10:45"
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *          ),
     *          @OA\Schema(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  type="array",
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Bad request",
     *          @OA\MediaType(
     *              mediaType="application/json"
     *          )
     *      )
     *   )
     */
    public function generateJitsi(ConsultationUserTermRequest $request, int $consultationTermId): JsonResponse;

    /**
     * @OA\Get(
     *      path="/api/consultations",
     *      summary="Get a listing of the Consultations.",
     *      tags={"Consultations"},
     *      description="Get all Consultations",
     *      @OA\Parameter(
     *          name="order_by",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *              enum={"id", "name", "status", "duration", "active_from", "active_to", "created_at"}
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="order",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *              enum={"ASC", "DESC"}
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="only_with_categories",
     *          description="Consultation has categories",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="boolean",
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="page",
     *          description="Pagination Page Number",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="number",
     *               default=1,
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="per_page",
     *          description="Pagination Per Page",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="number",
     *               default=15,
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="name",
     *          description="Consultation name %LIKE%",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="status",
     *          description="Consultation status == ",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\MediaType(
     *              mediaType="application/json"
     *          ),
     *          @OA\Schema(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  type="array",
     *                  @OA\Items(ref="#/components/schemas/Consultation")
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function index(ListAPIConsultationsRequest $listConsultationsRequest): JsonResponse;

    /**
     * @OA\Get(
     *      path="/api/consultations/me",
     *      security={
     *          {"passport": {}},
     *      },
     *      summary="Get a listing of the Consultations bought by user.",
     *      tags={"Consultations"},
     *      description="Get a listing of the Consultations bought by user",
     *      @OA\Parameter(
     *          name="order_by",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *              enum={"finished_at", "started_at", "created_at", "name"}
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="order",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *              enum={"ASC", "DESC"}
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="page",
     *          description="Pagination Page Number",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="number",
     *               default=1,
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="paginate",
     *          description="If true, list convert to paginate",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="boolean",
     *              default=false,
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="per_page",
     *          description="Pagination Per Page",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="number",
     *               default=15,
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="name",
     *          description="Consultation name %LIKE%",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="ids[]",
     *          description="Integer array",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="array",
     *              @OA\Items(
     *                  type="integer"
     *              )
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="status",
     *          description="Consultation status == ",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\MediaType(
     *              mediaType="application/json"
     *          ),
     *          @OA\Schema(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  type="array",
     *                  @OA\Items(ref="#/components/schemas/ConsultationTermForUserCurrent")
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function forCurrentUser(ListConsultationsRequest $listConsultationsRequest): JsonResponse;

    /**
     * @OA\Get(
     *      tags={"Consultations"},
     *      path="/api/consultations/proposed-terms/{orderItemId}",
     *      security={
     *          {"passport": {}},
     *      },
     *      description="Get proposedTerm For OrderItem",
     *      @OA\Parameter(
     *          name="orderItemId",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="integer",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *          ),
     *          @OA\Schema(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  type="array",
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Bad request",
     *          @OA\MediaType(
     *              mediaType="application/json"
     *          )
     *      )
     *   )
     */
    public function proposedTerms(int $orderItemId): JsonResponse;

    /**
     * @OA\Get(
     *      path="/api/consultations/{id}",
     *      summary="Display the specified Consultation",
     *      tags={"Consultations"},
     *      description="Get Consultation",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Consultation",
     *          @OA\Schema(
     *             type="integer",
     *         ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\MediaType(
     *              mediaType="application/json"
     *          ),
     *          @OA\Schema(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  ref="#/components/schemas/Consultation"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function show(ShowAPIConsultationRequest $showAPIConsultationRequest, int $id): JsonResponse;

    /**
     * @OA\Get(
     *      path="/api/consultations/my-schedule",
     *      tags={"Consultations"},
     *      description="Get Consultation schedule for tutor",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="ids[]",
     *          description="Consultation ID integer array",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="array",
     *              @OA\Items(
     *                  type="integer"
     *              )
     *          )
     *       ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\MediaType(
     *              mediaType="application/json"
     *          ),
     *          @OA\Schema(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  type="array",
     *                  @OA\Items(ref="#/components/schemas/ConsultationUserTerm")
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function schedule(ScheduleConsultationAPIRequest $scheduleConsultationAPIRequest): JsonResponse;

    /**
     * @OA\Post(
     *      path="/api/consultations/save-screen",
     *      security={
     *          {"passport": {}},
     *      },
     *      summary="Save screen from jitsi meeting",
     *      tags={"Consultations"},
     *      description="Report term consultation",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="multipart/form-data",
     *              @OA\Schema(
     *                  @OA\Property(
     *                      property="consultation_id",
     *                      type="int"
     *                  ),
     *                  @OA\Property(
     *                      property="user_termin_id",
     *                      type="int"
     *                  ),
     *                  @OA\Property(
     *                      property="user_email",
     *                      description="required_without:user_id",
     *                      type="string"
     *                  ),
     *                  @OA\Property(
     *                      property="user_id",
     *                      description="required_without:user_email",
     *                      type="integer"
     *                  ),
     *                  @OA\Property(
     *                      property="timestamp",
     *                      type="string",
     *                      example="2024-10-04 12:02:12"
     *                  ),
     *                  @OA\Property(
     *                      property="files",
     *                      type="array",
     *                      @OA\Items(
     *                          @OA\Property(
     *                              property="file",
     *                              type="string",
     *                              format="binary"
     *                          ),
     *                          @OA\Property(
     *                              property="timestamp",
     *                              type="string",
     *                              example="2024-10-04 12:02:12"
     *                          )
     *                       )
     *                 )
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\MediaType(
     *              mediaType="application/json"
     *          ),
     *          @OA\Schema(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  type="string"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Bad request",
     *          @OA\MediaType(
     *              mediaType="application/json"
     *          )
     *      )
     * )
     */
    public function screenSave(ConsultationScreenSaveRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *      path="/api/consultations/signed-screen-urls",
     *      security={
     *          {"passport": {}},
     *      },
     *      summary="Generate signed url to save screens from jitsi meeting",
     *      tags={"Consultations"},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(
     *                  @OA\Property(
     *                      property="consultation_id",
     *                      type="int"
     *                  ),
     *                  @OA\Property(
     *                      property="user_termin_id",
     *                      type="int"
     *                  ),
     *                  @OA\Property(
     *                      property="user_id",
     *                      type="integer"
     *                  ),
     *                  @OA\Property(
     *                      property="executed_at",
     *                      type="string",
     *                      example="2024-10-04 12:02:12"
     *                  ),
     *                  @OA\Property(
     *                      property="files",
     *                      type="array",
     *                      @OA\Items(
     *                          @OA\Property(
     *                              property="filename",
     *                              type="string",
     *                          ),
     *                       )
     *                 )
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\MediaType(
     *              mediaType="application/json"
     *          ),
     *          @OA\Schema(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  type="string"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Bad request",
     *          @OA\MediaType(
     *              mediaType="application/json"
     *          )
     *      )
     * )
     */
    public function generateSignedScreenUrls(GenerateSignedScreenUrlsRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *      path="/api/consultations/finish-term/{consultationTermId}",
     *      security={
     *          {"passport": {}},
     *      },
     *      summary="Finish consultation term",
     *      tags={"Consultations"},
     *      description="Finish consultation term",
     *      @OA\Parameter(
     *          name="consultationTermId",
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
     *              @OA\Schema(
     *                  @OA\Property(
     *                      property="finished_at",
     *                      type="string",
     *                      example="2024-10-04 12:02:12"
     *                  ),
     *                  @OA\Property(
     *                      property="term",
     *                      type="string",
     *                      example="2024-10-04 12:02:12"
     *                  )
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\MediaType(
     *              mediaType="application/json"
     *          ),
     *          @OA\Schema(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  type="string"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Bad request",
     *          @OA\MediaType(
     *              mediaType="application/json"
     *          )
     *      )
     * )
     */
    public function finishTerm(FinishTermRequest $request, int $consultationTermId): JsonResponse;
}
