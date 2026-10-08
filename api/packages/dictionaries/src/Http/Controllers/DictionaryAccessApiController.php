<?php

namespace EscolaLms\Dictionaries\Http\Controllers;

use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\Dictionaries\Http\Controllers\Swagger\DictionaryAccessApiControllerSwagger;
use EscolaLms\Dictionaries\Http\Resources\DictionaryAccessResource;
use EscolaLms\Dictionaries\Services\Contracts\DictionaryAccessServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DictionaryAccessApiController extends EscolaLmsBaseController implements DictionaryAccessApiControllerSwagger
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
