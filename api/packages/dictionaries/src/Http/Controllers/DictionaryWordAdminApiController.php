<?php

namespace Ulams\Dictionaries\Http\Controllers;

use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Dictionaries\Http\Controllers\Swagger\DictionaryWordAdminApiControllerSwagger;
use Ulams\Dictionaries\Http\Requests\DictionaryWord\Admin\CreateDictionaryWordRequest;
use Ulams\Dictionaries\Http\Requests\DictionaryWord\Admin\DeleteDictionaryWordRequest;
use Ulams\Dictionaries\Http\Requests\DictionaryWord\Admin\ImportDictionaryWordRequest;
use Ulams\Dictionaries\Http\Requests\DictionaryWord\Admin\ListDictionaryWordRequest;
use Ulams\Dictionaries\Http\Requests\DictionaryWord\Admin\ReadDictionaryWordRequest;
use Ulams\Dictionaries\Http\Requests\DictionaryWord\Admin\UpdateDictionaryWordRequest;
use Ulams\Dictionaries\Http\Resources\DictionaryWordResource;
use Ulams\Dictionaries\Http\Resources\DictionaryWordSimpleResource;
use Ulams\Dictionaries\Imports\DictionaryWordsImport;
use Ulams\Dictionaries\Services\Contracts\DictionaryWordServiceContract;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;

class DictionaryWordAdminApiController extends UlamsBaseController implements DictionaryWordAdminApiControllerSwagger
{

    public function __construct(private readonly DictionaryWordServiceContract $dictionaryWordService)
    {
    }

    public function index(ListDictionaryWordRequest $request): JsonResponse
    {
        $results = $this->dictionaryWordService->list($request->getCriteria(), $request->getPage(), $request->getOrder());

        return $this->sendResponseForResource(DictionaryWordSimpleResource::collection($results));
    }

    public function store(CreateDictionaryWordRequest $request): JsonResponse
    {
        $dictionary = $this->dictionaryWordService->create($request->toDto());

        return $this->sendResponseForResource(DictionaryWordResource::make($dictionary), __('Dictionary word created successfully'));
    }

    public function show(ReadDictionaryWordRequest $request): JsonResponse
    {
        return $this->sendResponseForResource(DictionaryWordResource::make($request->getDictionaryWord()));
    }

    public function update(UpdateDictionaryWordRequest $request): JsonResponse
    {
        $dictionary = $this->dictionaryWordService->update($request->getId(), $request->toDto());

        return $this->sendResponseForResource(DictionaryWordResource::make($dictionary), __('Dictionary word updated successfully'));
    }

    public function delete(DeleteDictionaryWordRequest $request): JsonResponse
    {
        $this->dictionaryWordService->delete($request->getDictionaryWord());

        return $this->sendSuccess(__('Dictionary word deleted successfully'));
    }

    public function import(ImportDictionaryWordRequest $request): JsonResponse
    {
        Excel::import((new DictionaryWordsImport($request->get('dictionary_id'))), $request->file('file'));

        return $this->sendSuccess( __('Dictionary word imported successfully'));
    }
}
