<?php

namespace Ulams\Tenancy\Http\Controllers\Swagger;

use Illuminate\Http\JsonResponse;
use Ulams\Tenancy\Http\Requests\ListPlatformRequest;

interface PlatformOperationSwagger
{
    /**
     * @OA\Get(path="/api/platform/operations", summary="The latest tenant operations", tags={"Platform"}, security={{"passport": {}}},
     *     @OA\Response(response=200, description="Up to 50 operations, newest first", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
     *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/PlatformOperation")))))
     */
    public function index(ListPlatformRequest $request): JsonResponse;

    /**
     * @OA\Get(path="/api/platform/operations/{id}", summary="Status and steps of a tenant operation (poll it)", tags={"Platform"}, security={{"passport": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="The operation", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
     *         @OA\Property(property="data", ref="#/components/schemas/PlatformOperation"))),
     *     @OA\Response(response=404, description="Unknown operation"))
     */
    public function show(ListPlatformRequest $request, string $id): JsonResponse;
}
