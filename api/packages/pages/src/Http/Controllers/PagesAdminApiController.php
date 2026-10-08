<?php

namespace EscolaLms\Pages\Http\Controllers;

use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\Pages\Dtos\PagesFilterCriteriaDto;
use EscolaLms\Pages\Http\Controllers\Contracts\PagesAdminApiContract;
use EscolaLms\Pages\Http\Requests\PageCreateRequest;
use EscolaLms\Pages\Http\Requests\PageDeleteRequest;
use EscolaLms\Pages\Http\Requests\PageListingRequest;
use EscolaLms\Pages\Http\Requests\PageReadRequest;
use EscolaLms\Pages\Http\Requests\PageUpdateRequest;
use EscolaLms\Pages\Http\Resources\PageResource;
use EscolaLms\Pages\Http\Services\Contracts\PageServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PagesAdminApiController extends EscolaLmsBaseController implements PagesAdminApiContract
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
