<?php

namespace EscolaLms\ConsultationAccess\Http\Controllers\Admin\Swagger;

use EscolaLms\ConsultationAccess\Http\Requests\Admin\AdminApproveConsultationAccessEnquiryRequest;
use EscolaLms\ConsultationAccess\Http\Requests\Admin\AdminDisapproveConsultationAccessEnquiryRequest;
use EscolaLms\ConsultationAccess\Http\Requests\Admin\AdminListConsultationAccessEnquiryRequest;
use Illuminate\Http\JsonResponse;

interface ConsultationAccessEnquiryAdminApiSwagger
{
    /**
     * @OA\Get(
     *     path="/api/admin/consultation-access-enquiries",
     *     summary="Get all consultation access enquiries",
     *     tags={"Admin Consultation Access"},
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
     *          name="consultation_id",
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
     *     @OA\Parameter(
     *          name="status",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *              enum={"pending", "approved"}
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="proposed_at_from",
     *          description="Proposed at from",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *              format="date-time",
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="proposed_at_to",
     *          description="Proposed at to",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *              format="date-time",
     *          ),
     *      ),
     *     @OA\Parameter(
     *          name="is_coming",
     *          required=false,
     *          in="query",
     *          description="approved future or past consultations",
     *          @OA\Schema(
     *              type="string",
     *              enum={"true", "false"}
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
     *                      @OA\Items(ref="#/components/schemas/ConsultationAccessEnquiryResource")
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
    public function index(AdminListConsultationAccessEnquiryRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *      path="/api/admin/consultation-access-enquiries/approve/{proposedTermId}",
     *      summary="Approve the specified consultation access enquiry by proposed term id",
     *      tags={"Admin Consultation Access"},
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="proposedTermId",
     *          description="proposedTermId",
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
     *              @OA\Schema(ref="#/components/schemas/AdminApproveConsultationAccessEnquiryRequest")
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
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function approve(AdminApproveConsultationAccessEnquiryRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *      path="/api/admin/consultation-access-enquiries/disapprove/{id}",
     *      summary="Disapprove the specified consultation access enquiry by id",
     *      tags={"Admin Consultation Access"},
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="id",
     *          description="id",
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
     *              @OA\Schema(ref="#/components/schemas/AdminDisapproveConsultationAccessEnquiryRequest")
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
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function disapprove(AdminDisapproveConsultationAccessEnquiryRequest $request): JsonResponse;
}
