<?php

namespace EscolaLms\Translations\Http\Controllers;

use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\Translations\Http\Controllers\Swagger\TranslationApiSwagger;
use EscolaLms\Translations\Http\Requests\PublicListLanguageLineRequest;
use EscolaLms\Translations\Http\Resources\LanguageLineResource;
use EscolaLms\Translations\Services\LanguageLineService;
use Illuminate\Http\JsonResponse;

class TranslationApiController extends EscolaLmsBaseController implements TranslationApiSwagger
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
