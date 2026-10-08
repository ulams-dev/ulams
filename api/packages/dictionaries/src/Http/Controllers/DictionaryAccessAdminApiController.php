<?php

namespace Ulams\Dictionaries\Http\Controllers;

use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Dictionaries\Http\Controllers\Swagger\DictionaryAccessAdminApiControllerSwagger;
use Ulams\Dictionaries\Http\Requests\DictionaryAccess\ListDictionaryAccessRequest;
use Ulams\Dictionaries\Http\Requests\DictionaryAccess\SetDictionaryAccessRequest;
use Ulams\Dictionaries\Http\Resources\DictionaryAccessAdminResource;
use Ulams\Dictionaries\Services\Contracts\DictionaryAccessServiceContract;
use Illuminate\Http\JsonResponse;

class DictionaryAccessAdminApiController extends UlamsBaseController implements DictionaryAccessAdminApiControllerSwagger
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
