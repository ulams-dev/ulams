<?php
namespace EscolaLms\Consultations\Http\Controllers\Swagger;

use EscolaLms\Consultations\Http\Requests\ChangeTermConsultationRequest;
use EscolaLms\Consultations\Http\Requests\ConsultationAssignableUserListRequest;
use EscolaLms\Consultations\Http\Requests\DestroyConsultationRequest;
use EscolaLms\Consultations\Http\Requests\ListConsultationsRequest;
use EscolaLms\Consultations\Http\Requests\ScheduleConsultationRequest;
use EscolaLms\Consultations\Http\Requests\ShowConsultationRequest;
use EscolaLms\Consultations\Http\Requests\StoreConsultationRequest;
use EscolaLms\Consultations\Http\Requests\UpdateConsultationRequest;
use Illuminate\Http\JsonResponse;

interface ConsultationSwagger
{
    /**
     * @OA\Get(
     *      path="/api/admin/consultations",
     *      tags={"Admin Consultations"},
     *      security={
     *          {"passport": {}},
     *      },
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
     *      @OA\Parameter(
     *          name="only_with_categories",
     *          description="Consultation has categories",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="boolean",
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
    public function index(ListConsultationsRequest $listConsultationsRequest): JsonResponse;

    /**
     * @OA\Post(
     *      path="/api/admin/consultations",
     *      summary="Store a newly created Consultation in storage",
     *      tags={"Admin Consultations"},
     *      description="Store Consultation",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(ref="#/components/schemas/Consultation")
     *          ),
     *      ),
     *      @OA\Response(
     *          response=201,
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
    public function store(StoreConsultationRequest $storeConsultationRequest): JsonResponse;

    /**
     * @OA\Get(
     *      path="/api/admin/consultations/{id}",
     *      summary="Display the specified Consultation",
     *      tags={"Admin Consultations"},
     *      description="Get Consultation",
     *      security={
     *         {"passport": {}},
     *      },
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
    public function show(ShowConsultationRequest $showConsultationRequest, int $id): JsonResponse;

    /**
     * @OA\Put(
     *      path="/api/admin/consultations/{id}",
     *      summary="Update the specified Consultation in storage",
     *      tags={"Admin Consultations"},
     *      description="Update Consultation",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Consultation",
     *          @OA\Schema(
     *             type="integer",
     *         ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(ref="#/components/schemas/Consultation")
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
    public function update(int $id, UpdateConsultationRequest $updateConsultationRequest): JsonResponse;

    /**
     * @OA\Delete(
     *      path="/api/admin/consultations/{id}",
     *      summary="Remove the specified Consultation from storage",
     *      tags={"Admin Consultations"},
     *      description="Delete Consultation",
     *      security={
     *          {"passport": {}},
     *      },
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
     *                  type="string"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function destroy(int $id, DestroyConsultationRequest $request): JsonResponse;

    /**
     * @OA\Get(
     *      path="/api/admin/consultations/{id}/schedule",
     *      tags={"Consultations"},
     *      description="Get Consultation schedule",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Consultation",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="integer",
     *          ),
     *          name="id",
     *      ),
     *      @OA\Parameter(
     *          name="date_from",
     *          description="Course term date from",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="date_to",
     *          description="Course term date to",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="status",
     *          description="Course term status: not_reported, reported, reject, approved",
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
     *                  @OA\Items(ref="#/components/schemas/ConsultationTerm")
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function schedule(int $id, ScheduleConsultationRequest $scheduleConsultationRequest): JsonResponse;

    /**
     * @OA\Post(
     *      path="/api/admin/consultations/change-term/{consultationTermId}",
     *      summary="Change term in consultation ",
     *      tags={"Admin Consultations"},
     *      description="Change term consultation",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="termId",
     *          description="id of Consultation Term",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="integer",
     *          ),
     *          name="id",
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(
     *                  property="executed_at",
     *                  type="string",
     *                  example="2024-10-31 10:45",
     *              ),
     *              @OA\Property(
     *                  property="term",
     *                  type="string",
     *                  example="2024-10-31 10:45",
     *              ),
     *              @OA\Property(
     *                  property="user_id",
     *                  type="int",
     *              ),
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
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
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */

    /**
     * @OA\Post(
     *      path="/api/consultations/change-term/{consultationTermId}",
     *      summary="Change term in consultation with tutor",
     *      tags={"Consultations"},
     *      description="Change term consultation with tutor",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="termId",
     *          description="id of Consultation Term",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="integer",
     *          ),
     *          name="id",
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(
     *                  property="executed_at",
     *                  type="string",
     *                  example="2024-10-31 10:45",
     *              ),
     *              @OA\Property(
     *                  property="term",
     *                  type="string",
     *                  example="2024-10-31 10:45",
     *              ),
     *              @OA\Property(
     *                  property="for_all_users",
     *                  type="boolean",
     *              ),
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
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
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function changeTerm(ChangeTermConsultationRequest $changeTermConsultationRequest, int $consultationTermId): JsonResponse;

    /**
     * @OA\Get(
     *      tags={"Admin Consultations"},
     *      path="/api/admin/consultations/users/assignable",
     *      description="Get users assignable to consultations",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="search",
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
     *              mediaType="application/json",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Bad request",
     *          @OA\MediaType(
     *              mediaType="application/json"
     *          )
     *      )
     *   )
     */
    public function assignableUsers(ConsultationAssignableUserListRequest $request): JsonResponse;
}
