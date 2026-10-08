<?php

namespace Ulams\Dictionaries\Http\Controllers;

use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Dictionaries\Http\Controllers\Swagger\DictionaryAccessApiControllerSwagger;
use Ulams\Dictionaries\Http\Resources\DictionaryAccessResource;
use Ulams\Dictionaries\Services\Contracts\DictionaryAccessServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DictionaryAccessApiController extends UlamsBaseController implements DictionaryAccessApiControllerSwagger
{
    public function __construct(private readonly DictionaryAccessServiceContract $dictionaryAccessService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $result = $this->dictionaryAccessService->getByUserId($request->user()->getKey());

        return $this->sendResponseForResource(DictionaryAccessResource::collection($result));
    }
}
