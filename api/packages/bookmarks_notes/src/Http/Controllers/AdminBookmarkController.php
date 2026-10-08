<?php

namespace Ulams\Bookmarks\Http\Controllers;

use Ulams\Bookmarks\Http\Controllers\Swagger\AdminBookmarkControllerSwagger;
use Ulams\Bookmarks\Http\Requests\AdminCreateBookmarkRequest;
use Ulams\Bookmarks\Http\Requests\AdminDeleteBookmarkRequest;
use Ulams\Bookmarks\Http\Requests\AdminListBookmarkRequest;
use Ulams\Bookmarks\Http\Requests\AdminUpdateBookmarkRequest;
use Ulams\Bookmarks\Http\Resources\BookmarkResource;
use Ulams\Bookmarks\Services\Contracts\BookmarkServiceContract;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Illuminate\Http\JsonResponse;

class AdminBookmarkController extends UlamsBaseController implements AdminBookmarkControllerSwagger
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
