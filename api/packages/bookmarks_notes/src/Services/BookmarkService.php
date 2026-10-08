<?php

namespace EscolaLms\Bookmarks\Services;

use EscolaLms\Bookmarks\Dtos\CreateBookmarkDto;
use EscolaLms\Bookmarks\Dtos\CriteriaDto;
use EscolaLms\Bookmarks\Dtos\OrderDto;
use EscolaLms\Bookmarks\Dtos\PageDto;
use EscolaLms\Bookmarks\Dtos\UpdateBookmarkDto;
use EscolaLms\Bookmarks\Models\Bookmark;
use EscolaLms\Bookmarks\Repositories\Contracts\BookmarkRepositoryContract;
use EscolaLms\Bookmarks\Services\Contracts\BookmarkServiceContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BookmarkService implements BookmarkServiceContract
{
    private BookmarkRepositoryContract $bookmarkRepository;

    public function __construct(BookmarkRepositoryContract $bookmarkRepository)
    {
        $this->bookmarkRepository = $bookmarkRepository;
    }

    public function create(CreateBookmarkDto $dto): Bookmark
    {
        return $this->bookmarkRepository->create($dto->toArray());
    }

    public function update(UpdateBookmarkDto $dto): Bookmark
    {
        return $this->bookmarkRepository->update($dto->toArray(), $dto->getId());
    }

    public function delete(int $id): void
    {
        $this->bookmarkRepository->delete($id);
    }

    public function findAll(CriteriaDto $criteria, PageDto $page, OrderDto $order): LengthAwarePaginator
    {
        return $this->bookmarkRepository->findAll(
            $criteria->toArray(),
            $page->getPerPage(),
            $order->getOrderDirection(),
            $order->getOrderBy()
        );
    }

    public function findAllUser(CriteriaDto $criteria, PageDto $page, OrderDto $order): LengthAwarePaginator
    {
        return $this->bookmarkRepository->findAllUser(
            auth()->id(),
            $criteria->toArray(),
            $page->getPerPage(),
            $order->getOrderDirection(),
            $order->getOrderBy()
        );
    }
}
