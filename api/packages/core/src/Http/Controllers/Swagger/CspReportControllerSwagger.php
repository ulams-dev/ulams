<?php

namespace Ulams\Core\Http\Controllers\Swagger;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Ulams\Core\Http\Requests\CspReportListRequest;

interface CspReportControllerSwagger
{
    /**
     * @OA\Post(
     *     path="/api/csp-report",
     *     summary="Collect a Content Security Policy violation report",
     *     tags={"Core"},
     *     description="Public, rate limited (60 per minute and IP), body up to 16 KB. Accepts `application/csp-report` (report-uri) and `application/reports+json` (Reporting API). Only the directive, the host of the blocked resource and the path of the page are kept, aggregated with a counter, and pruned after 30 days.",
     *     @OA\RequestBody(
     *         @OA\MediaType(mediaType="application/csp-report", @OA\Schema(type="object")),
     *         @OA\MediaType(mediaType="application/reports+json", @OA\Schema(type="array", @OA\Items(type="object"))),
     *     ),
     *     @OA\Response(response=204, description="Report accepted"),
     *     @OA\Response(response=400, description="The body is not a JSON report"),
     *     @OA\Response(response=413, description="The body is larger than 16 KB"),
     *     @OA\Response(response=415, description="Unsupported content type"),
     *     @OA\Response(response=429, description="Too many reports"),
     * )
     */
    public function store(Request $request): Response;

    /**
     * @OA\Get(
     *     path="/api/admin/csp-reports",
     *     summary="List the aggregated CSP violation reports",
     *     tags={"Core Admin"},
     *     security={
     *         {"passport": {}},
     *     },
     *     description="Admins only. Newest first.",
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=100)),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="directive", type="string", example="frame-src"),
     *                     @OA\Property(property="blocked_host", type="string", example="tool.example.test"),
     *                     @OA\Property(property="document_path", type="string", example="/learn/1/2"),
     *                     @OA\Property(property="count", type="integer"),
     *                     @OA\Property(property="first_seen_at", type="string", format="date-time"),
     *                     @OA\Property(property="last_seen_at", type="string", format="date-time"),
     *                 )
     *             ),
     *         ),
     *     ),
     *     @OA\Response(response=401, description="Endpoint requires authentication"),
     *     @OA\Response(response=403, description="User is not an admin"),
     * )
     */
    public function index(CspReportListRequest $request): JsonResponse;
}
