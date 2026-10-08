<?php

namespace EscolaLms\Payments\Http\Controllers\Swagger;

use EscolaLms\Payments\Http\Requests\PaymentShowRequest;
use EscolaLms\Payments\Http\Requests\PaymentsSearchRequest;
use EscolaLms\Payments\Models\Payment;
use Illuminate\Http\JsonResponse;

interface PaymentsSwagger
{
    /**
     * @OA\Get(
     *      path="/api/payments",
     *      summary="Search payments",
     *      tags={"Payments"},
     *      description="Get filtered and paginated Payments",
     *      security={
     *         {"passport": {}},
     *     },
     *      @OA\Parameter(
     *          name="order_by",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *              enum={"created_at", "updated_at", "status", "payable_id", "user_id", "amount", "order_id", "id"}
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
     *              default=1,
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="per_page",
     *          description="Pagination Per Page",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="number",
     *              default=15,
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="status",
     *          description="Payment status",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *              enum={"new", "paid", "cancelled"}
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="order_id",
     *          description="External order id (e.g. from payment provider)",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="payable_id",
     *          description="Id of payable (e.g. order id)",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="number",
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="payable_type",
     *          description="Full classname of payable model (eg. EscolaLms\Cart\Models\Order)",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="date_from",
     *          description="Date from",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *              format="date-time"
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="date_to",
     *          description="Date to",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *              format="date-time"
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
     *                  @OA\Items(ref="#/components/schemas/Payment")
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function search(PaymentsSearchRequest $request): JsonResponse;

    /**
     * @OA\Get(
     *      path="/api/payments/{id}",
     *      summary="Display the specified Payment",
     *      tags={"Payments"},
     *      description="Get Payment",
     *     security={
     *         {"passport": {}},
     *     },
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Payment",
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
     *                  ref="#/components/schemas/Payment"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function show(PaymentShowRequest $request, Payment $payment): JsonResponse;
}
