<?php

namespace Ulams\Tenancy\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Tenancy\Http\Controllers\Swagger\PlatformOperationSwagger;
use Ulams\Tenancy\Http\Requests\ListPlatformRequest;
use Ulams\Tenancy\Http\Resources\TenantOperationResource;
use Ulams\Tenancy\Models\TenantOperation;

class PlatformOperationController extends UlamsBaseController implements PlatformOperationSwagger
{
    public function index(ListPlatformRequest $request): JsonResponse
    {
        $operations = TenantOperation::query()->latest('created_at')->limit(50)->get();

        return $this->sendResponse(TenantOperationResource::collection($operations)->resolve($request), __('Tenant operations'));
    }

    public function show(ListPlatformRequest $request, string $id): JsonResponse
    {
        $operation = preg_match('/^[0-9a-z]{26}$/', $id) ? TenantOperation::query()->find($id) : null;
        if ($operation === null) {
            throw new NotFoundHttpException('Operation not found.');
        }

        return $this->sendResponse((new TenantOperationResource($operation))->resolve($request), __('Tenant operation'));
    }
}
