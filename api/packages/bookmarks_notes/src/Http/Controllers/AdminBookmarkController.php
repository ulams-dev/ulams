<?php

namespace EscolaLms\Bookmarks\Http\Controllers;

use EscolaLms\Bookmarks\Http\Controllers\Swagger\AdminBookmarkControllerSwagger;
use EscolaLms\Bookmarks\Http\Requests\AdminCreateBookmarkRequest;
use EscolaLms\Bookmarks\Http\Requests\AdminDeleteBookmarkRequest;
use EscolaLms\Bookmarks\Http\Requests\AdminListBookmarkRequest;
use EscolaLms\Bookmarks\Http\Requests\AdminUpdateBookmarkRequest;
use EscolaLms\Bookmarks\Http\Resources\BookmarkResource;
use EscolaLms\Bookmarks\Services\Contracts\BookmarkServiceContract;
use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use Illuminate\Http\JsonResponse;

class AdminBookmarkController extends EscolaLmsBaseController implements AdminBookmarkControllerSwagger
{
    private BookmarkServiceContract $bookmarkService;

    public function __construct(BookmarkServiceContract $bookmarkService)
    {
        $this->bookmarkService = $bookmarkService;
    }

    public function create(AdminCreateBookmarkRequest $request): JsonResponse
    {
        $bookmark = $this->bookmarkService->create($request->toDto());

        return $this->sendResponseForResource(BookmarkResource::make($bookmark), 'Bookmark created successfully.');
    }

    public function update(AdminUpdateBookmarkRequest $request): JsonResponse
    {
        $bookmark = $this->bookmarkService->update($request->toDto());

        return $this->sendResponseForResource(BookmarkResource::make($bookmark), 'Bookmark updated successfully.');
    }

    public function delete(AdminDeleteBookmarkRequest $request): JsonResponse
    {
        $this->bookmarkService->delete($request->getId());

        return $this->sendSuccess('Bookmark deleted successfully.');
    }

    public function findAll(AdminListBookmarkRequest $request): JsonResponse
    {
        $results = $this->bookmarkService->findAll($request->getCriteria(), $request->getPage(), $request->getOrder());

        return $this->sendResponseForResource(BookmarkResource::collection($results));
    }
}
