<?php

namespace EscolaLms\Vouchers\Http\Controllers\Swagger;

use EscolaLms\Vouchers\Http\Requests\ApplyCouponRequest;
use EscolaLms\Vouchers\Http\Requests\UnapplyCouponRequest;
use Illuminate\Http\JsonResponse;

interface VouchersApiControllerSwagger
{
    /**
     * @OA\Post(
     *      path="/api/cart/voucher",
     *      summary="Add Voucher Code to your Cart",
     *      tags={"Cart", "Vouchers"},
     *      description="Add Voucher Code to your Cart",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="multipart/form-data",
     *              @OA\Schema(
     *                  type="object",
     *                  @OA\Property(
     *                      property="code",
     *                      type="string",
     *                  )
     *              )
     *          ),
     *          @OA\MediaType(
     *              mediaType="multipart/json",
     *              @OA\Schema(
     *                  type="object",
     *                  @OA\Property(
     *                      property="code",
     *                      type="string",
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
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function apply(ApplyCouponRequest $request): JsonResponse;

    /**
     * @OA\Delete(
     *      path="/api/cart/voucher",
     *      summary="Remove Voucher from your Cart",
     *      tags={"Cart", "Vouchers"},
     *      description="Remove Voucher from your Cart",
     *      security={
     *          {"passport": {}},
     *      },
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
    public function unapply(UnapplyCouponRequest $request): JsonResponse;
}
