<?php

namespace Ulams\Bookmarks\Http\Controllers;

use Ulams\Bookmarks\Http\Controllers\Swagger\BookmarkControllerSwagger;
use Ulams\Bookmarks\Http\Requests\CreateBookmarkRequest;
use Ulams\Bookmarks\Http\Requests\DeleteBookmarkRequest;
use Ulams\Bookmarks\Http\Requests\ListBookmarkRequest;
use Ulams\Bookmarks\Http\Requests\UpdateBookmarkRequest;
use Ulams\Bookmarks\Http\Resources\BookmarkResource;
use Ulams\Bookmarks\Services\Contracts\BookmarkServiceContract;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Illuminate\Http\JsonResponse;

class BookmarkController extends UlamsBaseController implements BookmarkControllerSwagger
{
    private BookmarkServiceContract $bookmarkService;

    public function __construct(BookmarkServiceContract $bookmarkService)
    {
        $this->bookmarkService = $bookmarkService;
    }

    public function create(CreateBookmarkRequest $request): JsonResponse
    {
        $bookmark = $this->bookmarkService->create($request->toDto());

        return $this->sendResponseForResource(BookmarkResource::make($bookmark), 'Bookmark created successfully.');
    }

    public function update(UpdateBookmarkRequest $request): JsonResponse
    {
        $bookmark = $this->bookmarkService->update($request->toDto());

        return $this->sendResponseForResource(BookmarkResource::make($bookmark), 'Bookmark updated successfully.');
    }

    public function delete(DeleteBookmarkRequest $request): JsonResponse
    {
        $this->bookmarkService->delete($request->getId());

        return $this->sendSuccess('Bookmark deleted successfully.');
    }

    public function findAll(ListBookmarkRequest $request): JsonResponse
    {
        $results = $this->bookmarkService->findAllUser($request->getCriteria(), $request->getPage(), $request->getOrder());

        return $this->sendResponseForResource(BookmarkResource::collection($results));
    }
}
