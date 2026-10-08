<?php

namespace Ulams\Translations\Http\Controllers;

use Ulams\Core\Dtos\OrderDto;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Translations\Enum\ConstantEnum;
use Ulams\Translations\Http\Controllers\Swagger\TranslationAdminApiSwagger;
use Ulams\Translations\Http\Requests\CreateLanguageLineRequest;
use Ulams\Translations\Http\Requests\DeleteLanguageLineRequest;
use Ulams\Translations\Http\Requests\ListLanguageLineRequest;
use Ulams\Translations\Http\Requests\ReadLanguageLineRequest;
use Ulams\Translations\Http\Requests\RetrieveTranslationRequest;
use Ulams\Translations\Http\Requests\UpdateLanguageLineRequest;
use Ulams\Translations\Http\Resources\LanguageLineAdminResource;
use Ulams\Translations\Http\Resources\RetrieveTranslationResource;
use Ulams\Translations\Services\Contracts\LanguageLineServiceContract;
use Ulams\Translations\Services\Contracts\TranslationServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Lang;

class TranslationAdminApiController extends UlamsBaseController implements TranslationAdminApiSwagger
{
    private LanguageLineServiceContract $languageLineService;
    private TranslationServiceContract $translationService;

    public function __construct(
        LanguageLineServiceContract $languageLineService,
        TranslationServiceContract $translationService
    ) {
        $this->languageLineService = $languageLineService;
        $this->translationService = $translationService;
    }

    public function index(ListLanguageLineRequest $request): JsonResponse
    {
        $search = $request->except(['limit', 'skip', 'order', 'order_by']);
        $orderDto = OrderDto::instantiateFromRequest($request);

        $perPage = $request->get('per_page') ?? ConstantEnum::PER_PAGE;
        $languageLines = $this->languageLineService
            ->getList($orderDto, $search);

        return $this->sendResponseForResource(
            LanguageLineAdminResource::collection($perPage <= 0 ? $languageLines->get() : $languageLines->paginate($perPage)),
            __('Language lines retrieved successfully')
        );
    }

    public function store(CreateLanguageLineRequest $request): JsonResponse
    {
        $languageLine = $this->languageLineService->create($request->validated());

        return $this->sendResponseForResource(
            LanguageLineAdminResource::make($languageLine),
            __('Language line saved successfully')
        );
    }

    public function show(ReadLanguageLineRequest $request): JsonResponse
    {
        return $this->sendResponseForResource(LanguageLineAdminResource::make($request->getLanguageLine()));
    }

    public function update(UpdateLanguageLineRequest $request): JsonResponse
    {
        $languageLine = $this->languageLineService->update($request->getLanguageLine(), $request->validated());

        return $this->sendResponseForResource(
            LanguageLineAdminResource::make($languageLine),
            __('Language line updated successfully')
        );
    }

    public function delete(DeleteLanguageLineRequest $request): JsonResponse
    {
        if (!$this->languageLineService->delete($request->getLanguageLine())) {
            return $this->sendError(__('Error while deleting a language line'));
        }

        return $this->sendSuccess(__('Language line deleted successfully'));
    }

    public function translate(RetrieveTranslationRequest $request): JsonResponse
    {
        $result = $this->translationService->retrieve($request->getKey(), $request->getReplace());

        return $this->sendResponseForResource(
            RetrieveTranslationResource::collection($result),
            __('Retrieve translation successfully')
        );
    }
}
