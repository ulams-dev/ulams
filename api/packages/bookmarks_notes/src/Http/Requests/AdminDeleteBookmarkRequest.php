<?php

namespace EscolaLms\Bookmarks\Http\Requests;

use Illuminate\Support\Facades\Gate;

class AdminDeleteBookmarkRequest extends BookmarkRequest
{
    public function authorize(): bool
    {
        return Gate::allows('delete', $this->getBookmark());
    }

    public function getId(): ?int
    {
        return $this->route('id');
    }
}
