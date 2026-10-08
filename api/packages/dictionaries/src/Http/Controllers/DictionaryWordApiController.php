<?php

namespace Ulams\Dictionaries\Http\Controllers;

use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Dictionaries\Http\Controllers\Swagger\DictionaryWordApiControllerSwagger;
use Ulams\Dictionaries\Http\Requests\DictionaryWord\ListDictionaryWordRequest;
use Ulams\Dictionaries\Http\Resources\CategorySimpleResource;
use Ulams\Dictionaries\Http\Resources\DictionaryWordResource;
use Ulams\Dictionaries\Http\Resources\DictionaryWordSimpleResource;
use Ulams\Dictionaries\Services\Contracts\DictionaryWordServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DictionaryWordApiController extends UlamsBaseController implements DictionaryWordApiControllerSwagger
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
