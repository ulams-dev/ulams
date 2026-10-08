<?php

namespace EscolaLms\Bookmarks\Http\Controllers;

use EscolaLms\Bookmarks\Http\Controllers\Swagger\BookmarkControllerSwagger;
use EscolaLms\Bookmarks\Http\Requests\CreateBookmarkRequest;
use EscolaLms\Bookmarks\Http\Requests\DeleteBookmarkRequest;
use EscolaLms\Bookmarks\Http\Requests\ListBookmarkRequest;
use EscolaLms\Bookmarks\Http\Requests\UpdateBookmarkRequest;
use EscolaLms\Bookmarks\Http\Resources\BookmarkResource;
use EscolaLms\Bookmarks\Services\Contracts\BookmarkServiceContract;
use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use Illuminate\Http\JsonResponse;

class BookmarkController extends EscolaLmsBaseController implements BookmarkControllerSwagger
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
