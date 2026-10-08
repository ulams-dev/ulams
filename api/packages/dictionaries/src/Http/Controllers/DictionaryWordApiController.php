<?php

namespace EscolaLms\Dictionaries\Http\Controllers;

use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\Dictionaries\Http\Controllers\Swagger\DictionaryWordApiControllerSwagger;
use EscolaLms\Dictionaries\Http\Requests\DictionaryWord\ListDictionaryWordRequest;
use EscolaLms\Dictionaries\Http\Resources\CategorySimpleResource;
use EscolaLms\Dictionaries\Http\Resources\DictionaryWordResource;
use EscolaLms\Dictionaries\Http\Resources\DictionaryWordSimpleResource;
use EscolaLms\Dictionaries\Services\Contracts\DictionaryWordServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DictionaryWordApiController extends EscolaLmsBaseController implements DictionaryWordApiControllerSwagger
{

    public function __construct(private readonly DictionaryWordServiceContract $dictionaryWordService)
    {
    }

    public function index(ListDictionaryWordRequest $request): JsonResponse
    {
        $results = $this->dictionaryWordService->list($request->getCriteria(), $request->getPage(), $request->getOrder());

        return $this->sendResponseForResource(DictionaryWordSimpleResource::collection($results));
    }

    public function show(Request $request, string $slug, int $id): JsonResponse
    {
        $word = $this->dictionaryWordService->find($id, $request->user()?->id);

        return $this->sendResponseForResource(DictionaryWordResource::make($word));
    }

    public function categories(ListDictionaryWordRequest $request): JsonResponse
    {
        $result = $this->dictionaryWordService->categories($request->getCriteria());

        return $this->sendResponseForResource(CategorySimpleResource::collection($result));
    }
}
