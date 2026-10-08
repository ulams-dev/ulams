<?php

namespace Ulams\Translations\Http\Controllers;

use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Translations\Http\Controllers\Swagger\TranslationApiSwagger;
use Ulams\Translations\Http\Requests\PublicListLanguageLineRequest;
use Ulams\Translations\Http\Resources\LanguageLineResource;
use Ulams\Translations\Services\LanguageLineService;
use Illuminate\Http\JsonResponse;

class TranslationApiController extends UlamsBaseController implements TranslationApiSwagger
{
    private LanguageLineService $languageLineService;

    public function __construct(LanguageLineService $languageLineService)
    {
        $this->languageLineService = $languageLineService;
    }

    public function index(PublicListLanguageLineRequest $request): JsonResponse
    {
        $results = $this->languageLineService->getPublicLanguageLinesPaginatedList(
            $request->getCriteria(),
            $request->getPagination()
        );

        return $this->sendResponseForResource(
            LanguageLineResource::collection($results),
            __('Language lines retrieved successfully')
        );
    }
}
