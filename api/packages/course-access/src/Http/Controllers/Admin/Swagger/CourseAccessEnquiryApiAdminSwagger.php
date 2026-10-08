<?php

namespace EscolaLms\CourseAccess\Http\Controllers\Admin\Swagger;

use EscolaLms\CourseAccess\Http\Requests\Admin\AdminApproveCourseAccessEnquiry;
use EscolaLms\CourseAccess\Http\Requests\Admin\AdminDeleteCourseAccessEnquiryRequest;
use EscolaLms\CourseAccess\Http\Requests\Admin\AdminListCourseAccessEnquiryRequest;
use Illuminate\Http\JsonResponse;

interface CourseAccessEnquiryApiAdminSwagger
{
    /**
     * @OA\Get(
     *     path="/api/admin/course-access-enquiries",
     *     summary="Get all course access enquiries",
     *     tags={"Admin Course Access"},
     *     security={
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
     *          name="limit",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="integer",
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
     *     @OA\Parameter(
     *          name="status",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *              enum={"pending", "approved"}
     *          ),
     *      ),
     *     @OA\Parameter(
     *          name="order",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          ),
     *      ),
     *     @OA\Parameter(
     *          name="order_by",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
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
     *                      @OA\Items(ref="#/components/schemas/CourseAccessEnquiryResource")
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
    public function list(AdminListCourseAccessEnquiryRequest $request): JsonResponse;

    /**
     * @OA\Delete(
     *      path="/api/admin/course-access-enquiries/{id}",
     *      summary="Remove the specified Course Access Enquiry",
     *      tags={"Admin Course Access"},
     *      description="Delete Course Access Enquiry",
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
    public function delete(AdminDeleteCourseAccessEnquiryRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *      path="/api/admin/course-access-enquiries/approve/{id}",
     *      summary="Approve the specified Course Access Enquiry",
     *      tags={"Admin Course Access"},
     *      description="Approve Course Access Enquiry",
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
    public function approve(AdminApproveCourseAccessEnquiry $request): JsonResponse;
}
