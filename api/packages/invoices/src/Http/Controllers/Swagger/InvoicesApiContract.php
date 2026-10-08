<?php

namespace EscolaLms\Invoices\Http\Controllers\Swagger;

use EscolaLms\Invoices\Http\Requests\InvoicesReadRequest;
use Illuminate\Http\Response;

interface InvoicesApiContract
{
    /**
     * @OA\Get(
     *     path="/api/order-invoices/{id}",
     *     summary="Get invoice identified by a given id identifier of order",
     *     tags={"Invoices"},
     *     security={
     *         {"passport": {}},
     *     },
     *     @OA\Parameter(
     *         name="id",
     *         description="id of Order",
     *         @OA\Schema(
     *            type="integer",
     *         ),
     *         required=true,
     *         in="path"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="",
     *      ),
     *     @OA\Response(
     *          response=401,
     *          description="endpoint requires authentication",
     *     ),
     *     @OA\Response(
     *          response=403,
     *          description="user doesn't have required access rights",
     *      ),
     *     @OA\Response(
     *          response=500,
     *          description="server-side error",
     *      ),
     * )
     */
    public function read(InvoicesReadRequest $request): Response;
}
