<?php

namespace EscolaLms\Vouchers\Http\Controllers\Swagger;

use EscolaLms\Vouchers\Http\Requests\CreateCouponRequest;
use EscolaLms\Vouchers\Http\Requests\DeleteCouponRequest;
use EscolaLms\Vouchers\Http\Requests\ListCouponsRequest;
use EscolaLms\Vouchers\Http\Requests\ReadCouponRequest;
use EscolaLms\Vouchers\Http\Requests\UpdateCouponRequest;
use Illuminate\Http\JsonResponse;

interface VouchersAdminApiControllerSwagger
{
    /**
     * @OA\Get(
     *      path="/api/admin/vouchers",
     *      summary="Get a listing of the Vouchers.",
     *      tags={"Admin Vouchers"},
     *      description="Get all Vouchers",
     *      security={
     *          {"passport": {}},
     *      },
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
     *                  @OA\Items(ref="#/components/schemas/Coupon")
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function index(ListCouponsRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *      path="/api/admin/vouchers",
     *      summary="Create Voucher",
     *      tags={"Admin Vouchers"},
     *      description="Create Voucher",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(ref="#/components/schemas/Coupon")
     *          ),
     *          @OA\MediaType(
     *              mediaType="multipart/form-data",
     *              @OA\Schema(ref="#/components/schemas/Coupon")
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
     *                  ref="#/components/schemas/Coupon"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function create(CreateCouponRequest $request): JsonResponse;

    /**
     * @OA\Get(
     *      path="/api/admin/vouchers/{id}",
     *      summary="Read Voucher",
     *      tags={"Admin Vouchers"},
     *      description="Read Voucher",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Voucher",
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
     *                  ref="#/components/schemas/Coupon"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function read(ReadCouponRequest $request): JsonResponse;

    /**
     * @OA\Patch(
     *      path="/api/admin/vouchers/{id}",
     *      summary="Update Voucher",
     *      tags={"Admin Vouchers"},
     *      description="Update Voucher",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Voucher",
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
     *              @OA\Schema(ref="#/components/schemas/Coupon")
     *          ),
     *          @OA\MediaType(
     *              mediaType="multipart/form-data",
     *              @OA\Schema(ref="#/components/schemas/Coupon")
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
     *                  ref="#/components/schemas/Coupon"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function update(UpdateCouponRequest $request): JsonResponse;

    /**
     * @OA\Delete(
     *      path="/api/admin/vouchers/{id}",
     *      summary="Delete Voucher",
     *      tags={"Admin Vouchers"},
     *      description="Delete Voucher",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Voucher",
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
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function delete(DeleteCouponRequest $request): JsonResponse;
}
