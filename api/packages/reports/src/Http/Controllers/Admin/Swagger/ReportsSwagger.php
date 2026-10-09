<?php

namespace Ulams\Reports\Http\Controllers\Admin\Swagger;

use Ulams\Reports\Actions\FindReport;
use Ulams\Reports\Http\Requests\Admin\ReportRequest;
use Illuminate\Http\JsonResponse;

interface ReportsSwagger
{
    /**
     * @OA\Get(
     *     path="/api/admin/reports/metrics",
     *     summary="Get list of available metrics",
     *     description="",
     *     tags={"Admin Reports"},
     *      security={
     *          {"passport": {}},
     *      },
     *     @OA\Response(
     *          response=200,
     *          description="successful operation, returns User data",
     *          @OA\JsonContent(
     *                  type="array",
     *                  @OA\Items(
     *                          type="string"
     *                  )
     *          )
     *     ),
     * )
     */
    public function metrics(): JsonResponse;

    /**
     * @OA\Get(
     *     path="/api/admin/reports/report",
     *     summary="Get list of available metrics",
     *     description="",
     *     tags={"Admin Reports"},
     *      security={
     *          {"passport": {}},
     *      },
     *     @OA\Parameter(
     *          name="metric",
     *          required=true,
     *          in="query",
     *          description="one of available metrics",
     *          @OA\Schema(
     *              type="string",
     *          ),
     *      ),
     *     @OA\Parameter(
     *          name="date",
     *          required=false,
     *          in="query",
     *          description="date from which report should be loaded (if there exists historical data), limit will be ignored and only datapoints with which record was created will be loaded; don't send this param to always get fresh report; send today date if you want to get latest stored report",
     *          @OA\Schema(
     *              type="datetime",
     *          ),
     *      ),
     *     @OA\Parameter(
     *          name="limit",
     *          required=false,
     *          in="query",
     *          description="how many datapoints should be returned. To get all datapoints set limit as -1",
     *          @OA\Schema(
     *              type="integer",
     *          ),
     *      ),
     *     @OA\Response(
     *          response=200,
     *          description="successful operation, returns User data",
     *          @OA\JsonContent(
     *                  type="array",
     *                  @OA\Items(
     *                          type="string"
     *                  )
     *          )
     *     ),
     * )
     */
    public function report(ReportRequest $request, FindReport $action): JsonResponse;

    /**
     * @OA\Get(
     *     path="/api/admin/reports/available-for-user",
     *     summary="Get list of available metrics for logged user",
     *     description="",
     *     tags={"Admin Reports"},
     *      security={
     *          {"passport": {}},
     *      },
     *     @OA\Response(
     *          response=200,
     *          description="successful operation, returns User data",
     *          @OA\JsonContent(
     *                  type="array",
     *                  @OA\Items(
     *                          type="string"
     *                  )
     *          )
     *     ),
     * )
     */
    public function availableForUser(): JsonResponse;
}
