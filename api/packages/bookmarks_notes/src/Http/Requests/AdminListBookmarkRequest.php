<?php

namespace Ulams\Bookmarks\Http\Requests;

use Ulams\Bookmarks\Dtos\CriteriaDto;
use Ulams\Bookmarks\Dtos\OrderDto;
use Ulams\Bookmarks\Dtos\PageDto;
use Ulams\Bookmarks\Models\Bookmark;
use Illuminate\Support\Facades\Gate;

class AdminListBookmarkRequest extends BookmarkRequest
{
    public function authorize(): bool
    {
        return Gate::allows('list', Bookmark::class);
    }

    public function getPage(): PageDto
    {
        return PageDto::instantiateFromRequest($this);
    }

    public function getOrder(): OrderDto
    {
        return OrderDto::instantiateFromRequest($this);
    }

    public function getCriteria(): CriteriaDto
    {
        return CriteriaDto::instantiateFromRequest($this);
    }
}
