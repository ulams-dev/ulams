<?php

namespace Ulams\ModelFields\Http\Controllers;

use Ulams\Core\Dtos\OrderDto;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Illuminate\Http\JsonResponse;
use Ulams\ModelFields\Services\Contracts\ModelFieldsServiceContract;
use Ulams\ModelFields\Http\Controllers\Contracts\ModelFieldsApiContract;
use Ulams\ModelFields\Http\Resources\MetadataResource;
use Ulams\ModelFields\Http\Requests\MetadataCreateOrUpdateRequest;
use Ulams\ModelFields\Http\Requests\MetadataDeleteRequest;
use Ulams\ModelFields\Http\Requests\MetadataListRequest;

class ModelFieldsApiController extends UlamsBaseController implements ModelFieldsApiContract
{
    private ModelFieldsServiceContract $service;

    public function __construct(ModelFieldsServiceContract $service)
    {
        $this->service = $service;
    }

    public function list(MetadataListRequest $request): JsonResponse
    {
        /** @var string|false $classType */
        $classType = $request->get('class_type');
        if (empty($classType)) {
            return $this->sendError("class_type is required", 400);
        }
        $metaFields = $this->service->getFieldsMetadata($classType);
        return $this->sendResponseForResource(MetadataResource::collection($metaFields), "metaFields list retrieved successfully");
    }
}
