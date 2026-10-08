<?php

namespace EscolaLms\Dictionaries\Http\Controllers;

use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\Dictionaries\Http\Controllers\Swagger\DictionaryAccessAdminApiControllerSwagger;
use EscolaLms\Dictionaries\Http\Requests\DictionaryAccess\ListDictionaryAccessRequest;
use EscolaLms\Dictionaries\Http\Requests\DictionaryAccess\SetDictionaryAccessRequest;
use EscolaLms\Dictionaries\Http\Resources\DictionaryAccessAdminResource;
use EscolaLms\Dictionaries\Services\Contracts\DictionaryAccessServiceContract;
use Illuminate\Http\JsonResponse;

class DictionaryAccessAdminApiController extends EscolaLmsBaseController implements DictionaryAccessAdminApiControllerSwagger
{
    public function __construct(private readonly DictionaryAccessServiceContract $dictionaryAccessService)
    {
    }

    public function index(ListDictionaryAccessRequest $request): JsonResponse
    {
        $results = $this->dictionaryAccessService->getByDictionaryId($request->getId());

        return $this->sendResponseForResource(DictionaryAccessAdminResource::collection($results));
    }

    public function set(SetDictionaryAccessRequest $request): JsonResponse
    {
        $this->dictionaryAccessService->setAccess($request->getDictionary(), $request->toDto());

        return $this->sendSuccess(__('Access list saved successfully'));
    }
}
