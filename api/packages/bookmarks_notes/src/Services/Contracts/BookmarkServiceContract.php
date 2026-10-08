<?php

namespace EscolaLms\Bookmarks\Services\Contracts;

use EscolaLms\Bookmarks\Dtos\CreateBookmarkDto;
use EscolaLms\Bookmarks\Dtos\CriteriaDto;
use EscolaLms\Bookmarks\Dtos\OrderDto;
use EscolaLms\Bookmarks\Dtos\PageDto;
use EscolaLms\Bookmarks\Dtos\UpdateBookmarkDto;
use EscolaLms\Bookmarks\Models\Bookmark;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface BookmarkServiceContract
{
    public function create(CreateBookmarkDto $dto): Bookmark;

    public function update(UpdateBookmarkDto $dto): Bookmark;

    public function delete(int $id): void;

    public function findAll(CriteriaDto $criteria, PageDto $page, OrderDto $order): LengthAwarePaginator;

    public function findAllUser(CriteriaDto $criteria, PageDto $page, OrderDto $order): LengthAwarePaginator;
}
