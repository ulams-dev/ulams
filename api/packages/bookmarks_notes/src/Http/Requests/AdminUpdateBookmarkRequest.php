<?php

namespace Ulams\Bookmarks\Http\Requests;

use Ulams\Bookmarks\Dtos\UpdateBookmarkDto;
use Illuminate\Support\Facades\Gate;

class AdminUpdateBookmarkRequest extends BookmarkRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->getBookmark());
    }

    public function rules(): array
    {
        return [
            'value' => ['nullable', 'string'],
            'bookmarkable_id' => ['required', 'integer'],
            'bookmarkable_type' => ['required', 'string'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    public function toDto(): UpdateBookmarkDto
    {
        return UpdateBookmarkDto::instantiateFromRequest($this);
    }
}
