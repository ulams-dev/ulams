<?php

namespace EscolaLms\ConsultationAccess\Http\Controllers\Swagger;

use EscolaLms\ConsultationAccess\Http\Requests\CreateConsultationAccessEnquiryRequest;
use EscolaLms\ConsultationAccess\Http\Requests\DeleteConsultationAccessEnquiryRequest;
use EscolaLms\ConsultationAccess\Http\Requests\ListConsultationAccessEnquiryRequest;
use EscolaLms\ConsultationAccess\Http\Requests\ReadConsultationAccessEnquiryRequest;
use EscolaLms\ConsultationAccess\Http\Requests\UpdateConsultationAccessEnquiryRequest;
use Illuminate\Http\JsonResponse;

interface ConsultationAccessEnquiryApiSwagger
{
    /**
     * @OA\Get(
     *      path="/api/consultation-access-enquiries",
     *     summary="Get my consultation access enquiries",
     *     tags={"Consultation Access"},
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
     *      @OA\Parameter(
     *          name="consultation_term_ids[]",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="array",
     *              @OA\Items(
     *                  @OA\Schema(
     *                      type="integer"
     *                  ),
     *              ),
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
    public function index(ListConsultationAccessEnquiryRequest $request): JsonResponse;

    /**
     * @OA\Get(
     *      path="/api/consultation-access-enquiries/{id}",
     *     summary="Get my consultation access enquiry by id",
     *     tags={"Consultation Access"},
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
     *                      ref="#/components/schemas/ConsultationAccessEnquiryResource"
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
    public function read(ReadConsultationAccessEnquiryRequest $request): JsonResponse;

    /**
     * @OA\Get(
     *      path="/api/consultation-access-enquiries/{id}/join",
     *     summary="Get url to consultation by enquiry id",
     *     tags={"Consultation Access"},
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
     *                      ref="#/components/schemas/JoinConsultationAccessResource"
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
    public function join(ReadConsultationAccessEnquiryRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *      path="/api/consultation-access-enquiries",
     *      summary="Store a newly Consultation Access Enqiury",
     *      tags={"Consultation Access"},
     *      description="Store Consultation Access Enqiury",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(ref="#/components/schemas/CreateConsultationAccessEnquiryRequest")
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
     *                      ref="#/components/schemas/ConsultationAccessEnquiryResource"
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
    public function create(CreateConsultationAccessEnquiryRequest $request): JsonResponse;

    /**
     * @OA\Delete(
     *      path="/api/consultation-access-enquiries/{id}",
     *      summary="Remove the specified Consultation Access Enquiry",
     *      tags={"Consultation Access"},
     *      description="Delete Consultation Access Enquiry",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="id",
     *          description="ID",
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
    public function delete(DeleteConsultationAccessEnquiryRequest $request): JsonResponse;

    /**
     * @OA\Patch(
     *      path="/api/consultation-access-enquiries/{id}",
     *      summary="Update Consultation Access Enqiury",
     *      tags={"Consultation Access"},
     *      description="Update Consultation Access Enqiury",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="id",
     *          description="ID",
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
     *              @OA\Schema(ref="#/components/schemas/UpdateConsultationAccessEnquiryRequest")
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
     *                      ref="#/components/schemas/ConsultationAccessEnquiryResource"
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
    public function update(UpdateConsultationAccessEnquiryRequest $request): JsonResponse;
}
