<?php

namespace Ulams\Pages\Http\Controllers;

use Ulams\Core\Dtos\OrderDto;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Pages\Dtos\PagesFilterCriteriaDto;
use Ulams\Pages\Http\Controllers\Contracts\PagesAdminApiContract;
use Ulams\Pages\Http\Requests\PageCreateRequest;
use Ulams\Pages\Http\Requests\PageDeleteRequest;
use Ulams\Pages\Http\Requests\PageListingRequest;
use Ulams\Pages\Http\Requests\PageReadRequest;
use Ulams\Pages\Http\Requests\PageUpdateRequest;
use Ulams\Pages\Http\Resources\PageResource;
use Ulams\Pages\Http\Services\Contracts\PageServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PagesAdminApiController extends UlamsBaseController implements PagesAdminApiContract
{
    private PageServiceContract $pageService;

    public function __construct(PageServiceContract $pageService)
    {
        $this->pageService = $pageService;
    }

    public function list(PageListingRequest $request): JsonResponse
    {
        $pages = $this->pageService->list(
            PagesFilterCriteriaDto::instantiateFromRequest($request)->toArray(),
            OrderDto::instantiateFromRequest($request)
        );

        return $this->sendResponseForResource(
            PageResource::collection($pages),
            __("pages list retrieved successfully")
        );
    }

    public function create(PageCreateRequest $request): JsonResponse
    {
        $slug = $request->getParamSlug();
        $title = $request->getParamTitle();
        $content = $request->getParamContent();
        $active = $request->get('active');

        $user = Auth::user();
        $page = $this->pageService->insert($slug, $title, $content, $user->id, $active);
        return $this->sendResponseForResource(PageResource::make($page), __("page created successfully"));
    }

    public function update(PageUpdateRequest $request, int $id): JsonResponse
    {
        $input = $request->all();
        $updated = $this->pageService->update($id, $input);

        return $this->sendResponseForResource(PageResource::make($updated), __("page updated successfully"));
    }

    public function delete(PageDeleteRequest $request, int $id): JsonResponse
    {
        $deleted = $this->pageService->deleteById($id);

        return $this->sendResponse($deleted, __("page updated successfully"));
    }

    public function read(PageReadRequest $request, int $id): JsonResponse
    {
        $page = $this->pageService->getById($id);

        return $this->sendResponseForResource(PageResource::make($page), __("page fetched successfully"));
    }
}
