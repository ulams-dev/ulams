<?php

namespace Ulams\Pages\Http\Controllers;

use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Pages\Http\Controllers\Contracts\PagesApiContract;
use Ulams\Pages\Http\Requests\PageFrontListingRequest;
use Ulams\Pages\Http\Requests\PageFrontReadRequest;
use Ulams\Pages\Http\Resources\PageResource;
use Ulams\Pages\Http\Services\Contracts\PageServiceContract;
use Illuminate\Http\JsonResponse;

class PagesApiController extends UlamsBaseController implements PagesApiContract
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
