<?php

namespace Ulams\ModelFields\Http\Controllers;

use Ulams\Core\Dtos\OrderDto;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\ModelFields\Http\Controllers\Contracts\ModelFieldsAdminApiContract;
use Ulams\ModelFields\Http\Requests\MetadataCreateOrUpdateRequest;
use Ulams\ModelFields\Http\Requests\MetadataDeleteRequest;
use Ulams\ModelFields\Http\Requests\MetadataListRequest;
use Ulams\ModelFields\Http\Resources\MetadataResource;
use Ulams\ModelFields\Services\Contracts\ModelFieldsServiceContract;
use Illuminate\Http\JsonResponse;

class ModelFieldsAdminApiController extends UlamsBaseController implements ModelFieldsAdminApiContract
{
    private ModelFieldsServiceContract $service;

    public function __construct(ModelFieldsServiceContract $service)
    {
        $this->service = $service;
    }

    public function list(MetadataListRequest $request): JsonResponse
    {
        /** @var string $classType */
        $classType = $request->get('class_type');
        if (empty($classType)) {
            return $this->sendError("class_type is required", 400);
        }
        /** @var int $perPage */
        $perPage = $request->get('per_page', 15);
        $metaFields = $this->service->getFieldsMetadataListPaginated($classType, $perPage, OrderDto::instantiateFromRequest($request));
        return $this->sendResponseForResource(MetadataResource::collection($metaFields), "metaFields list retrieved successfully");
    }

    public function createOrUpdate(MetadataCreateOrUpdateRequest $request): JsonResponse
    {
        $input = $request->all();

        $field = $this->service->addOrUpdateMetadataField(
            $input['class_type'],
            $input['name'],
            $input['type'],
            $input['default'] ?? '',
            isset($input['rules']) ? json_decode($input['rules']) : null,
            1 << 0,
            isset($input['extra']) ? json_decode($input['extra']) : null,
        );

        return $this->sendResponseForResource(MetadataResource::make($field), "meta field created or updated successfully");
    }

    public function delete(MetadataDeleteRequest $request): JsonResponse
    {
        $input = $request->all();

        $bool = $this->service->removeMetaField(
            $input['class_type'],
            $input['name'],
        );

        return $bool ? $this->sendResponse(true, "meta field deleted successfully") : $this->sendError("meta field delete error", 404);
    }
}
