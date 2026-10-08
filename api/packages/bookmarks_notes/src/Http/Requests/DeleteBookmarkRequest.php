<?php

namespace Ulams\Bookmarks\Http\Requests;

use Illuminate\Support\Facades\Gate;

class DeleteBookmarkRequest extends BookmarkRequest
{
    public function authorize(): bool
    {
        return Gate::allows('deleteOwn', $this->getBookmark());
    }

    public function getId(): ?int
    {
        return $this->route('id');
    }
}
