<?php

namespace EscolaLms\Bookmarks\Http\Requests;

use EscolaLms\Bookmarks\Dtos\CriteriaDto;
use EscolaLms\Bookmarks\Dtos\OrderDto;
use EscolaLms\Bookmarks\Dtos\PageDto;
use EscolaLms\Bookmarks\Models\Bookmark;
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
