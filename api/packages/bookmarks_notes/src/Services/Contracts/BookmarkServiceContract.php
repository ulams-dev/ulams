<?php

namespace Ulams\Bookmarks\Services\Contracts;

use Ulams\Bookmarks\Dtos\CreateBookmarkDto;
use Ulams\Bookmarks\Dtos\CriteriaDto;
use Ulams\Bookmarks\Dtos\OrderDto;
use Ulams\Bookmarks\Dtos\PageDto;
use Ulams\Bookmarks\Dtos\UpdateBookmarkDto;
use Ulams\Bookmarks\Models\Bookmark;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface BookmarkServiceContract
{
    public function create(CreateBookmarkDto $dto): Bookmark;

    public function update(UpdateBookmarkDto $dto): Bookmark;

    public function delete(int $id): void;

    public function findAll(CriteriaDto $criteria, PageDto $page, OrderDto $order): LengthAwarePaginator;

    public function findAllUser(CriteriaDto $criteria, PageDto $page, OrderDto $order): LengthAwarePaginator;
}
