<?php

namespace EscolaLms\Dictionaries\Http\Controllers;

use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\Dictionaries\Http\Controllers\Swagger\DictionaryAdminApiControllerSwagger;
use EscolaLms\Dictionaries\Http\Requests\Dictionary\CreateDictionaryRequest;
use EscolaLms\Dictionaries\Http\Requests\Dictionary\DeleteDictionaryRequest;
use EscolaLms\Dictionaries\Http\Requests\Dictionary\ListDictionaryRequest;
use EscolaLms\Dictionaries\Http\Requests\Dictionary\ReadDictionaryRequest;
use EscolaLms\Dictionaries\Http\Requests\Dictionary\UpdateDictionaryRequest;
use EscolaLms\Dictionaries\Http\Resources\DictionaryResource;
use EscolaLms\Dictionaries\Services\Contracts\DictionaryServiceContract;
use Illuminate\Http\JsonResponse;

class DictionaryAdminApiController extends EscolaLmsBaseController implements DictionaryAdminApiControllerSwagger
{
    public function __construct(private readonly DictionaryServiceContract $dictionaryService)
    {
    }

    public function index(ListDictionaryRequest $request): JsonResponse
    {
        $results = $this->dictionaryService->list($request->getCriteria(), $request->getPage(), $request->getOrder());

        return $this->sendResponseForResource(DictionaryResource::collection($results));
    }

    public function store(CreateDictionaryRequest $request): JsonResponse
    {
        $dictionary = $this->dictionaryService->create($request->toDto());

        return $this->sendResponseForResource(DictionaryResource::make($dictionary), __('Dictionary created successfully'));
    }

    public function show(ReadDictionaryRequest $request): JsonResponse
    {
        return $this->sendResponseForResource(DictionaryResource::make($request->getDictionary()));
    }

    public function update(UpdateDictionaryRequest $request): JsonResponse
    {
        $dictionary = $this->dictionaryService->update($request->getId(), $request->toDto());

        return $this->sendResponseForResource(DictionaryResource::make($dictionary), __('Dictionary updated successfully'));
    }

    public function delete(DeleteDictionaryRequest $request): JsonResponse
    {
        $this->dictionaryService->delete($request->getDictionary());

        return $this->sendSuccess(__('Dictionary deleted successfully'));
    }
}
