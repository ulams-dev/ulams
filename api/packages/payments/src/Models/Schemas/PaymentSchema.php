<?php

namespace EscolaLms\Payments\Models\Schemas;

/**
 * @OA\Schema(
 *      schema="Payment",
 *      required={"amount", "currency", "description"},
 *      @OA\Property(
 *          property="id",
 *          description="id",
 *          type="integer",
 *      ),
 *      @OA\Property(
 *          property="amount",
 *          description="amount",
 *          type="integer"
 *      ),
 *      @OA\Property(
 *          property="currency",
 *          description="currency",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="description",
 *          description="description",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="order_id",
 *          description="order_id",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="payable_id",
 *          description="payable_id",
 *          type="integer"
 *      ),
 *      @OA\Property(
 *          property="payable_type",
 *          description="payable_type",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="user_id",
 *          description="user_id",
 *          type="integer"
 *      ),
 * )
 */
interface PaymentSchema
{
}
