<?php

namespace Ulams\Vouchers\Http\Controllers\Swagger;

use Ulams\Vouchers\Http\Requests\ApplyCouponRequest;
use Ulams\Vouchers\Http\Requests\UnapplyCouponRequest;
use Illuminate\Http\JsonResponse;

interface VouchersApiControllerSwagger
{
    /**
     * @OA\Post(
     *      path="/api/cart/voucher", operationId="cart.voucher.post",
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
     *          @OA\JsonContent(ref="#/components/schemas/ApiEnvelope"),
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
     *      path="/api/cart/voucher", operationId="cart.voucher.delete",
     *      summary="Remove Voucher from your Cart",
     *      tags={"Cart", "Vouchers"},
     *      description="Remove Voucher from your Cart",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(ref="#/components/schemas/ApiEnvelope"),
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
