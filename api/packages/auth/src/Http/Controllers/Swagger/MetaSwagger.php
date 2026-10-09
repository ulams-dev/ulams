<?php

namespace Ulams\Auth\Http\Controllers\Swagger;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

interface MetaSwagger
{
    /**
     * @OA\Get(path="/api/meta", summary="Capabilities of this host", tags={"Auth"},
     *     @OA\Response(response=200, description="API name, version, contract, host kind and feature flags", @OA\JsonContent(
     *         @OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
     *         @OA\Property(property="data", type="object",
     *             @OA\Property(property="api", type="string", example="ulams"), @OA\Property(property="version", type="string"),
     *             @OA\Property(property="contract", type="integer", example=1), @OA\Property(property="host", type="string"),
     *             @OA\Property(property="kind", type="string", enum={"tenant","platform"}),
     *             @OA\Property(property="features", type="object",
     *                 @OA\Property(property="ai", type="boolean"), @OA\Property(property="courseBuilder", type="boolean"),
     *                 @OA\Property(property="livingCourse", type="boolean"), @OA\Property(property="deviceLogin", type="boolean"),
     *                 @OA\Property(property="scopedTokens", type="boolean"), @OA\Property(property="idempotency", type="boolean"),
     *                 @OA\Property(property="platformApi", type="boolean"), @OA\Property(property="demo", type="boolean"))))))
     */
    public function show(Request $request): JsonResponse;
}
