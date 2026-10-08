<?php

namespace EscolaLms\Pages\Http\Controllers;

use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\Pages\Http\Controllers\Contracts\PagesApiContract;
use EscolaLms\Pages\Http\Requests\PageFrontListingRequest;
use EscolaLms\Pages\Http\Requests\PageFrontReadRequest;
use EscolaLms\Pages\Http\Resources\PageResource;
use EscolaLms\Pages\Http\Services\Contracts\PageServiceContract;
use Illuminate\Http\JsonResponse;

class PagesApiController extends EscolaLmsBaseController implements PagesApiContract
{
    private PageServiceContract $pageService;

    public function __construct(PageServiceContract $pageService)
    {
        $this->pageService = $pageService;
    }

    public function list(PageFrontListingRequest $request): JsonResponse
    {
        $pages = $this->pageService->search(['active' => true]);

        return $this->sendResponseForResource(
            PageResource::collection($pages),
            __("pages list retrieved successfully")
        );
    }

    public function read(PageFrontReadRequest $request): JsonResponse
    {
        $page = $this->pageService->getBySlug($request->getParamSlug());

        return $this->sendResponseForResource(PageResource::make($page), __("page fetched successfully"));
    }
}
