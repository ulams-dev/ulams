<?php

namespace Ulams\Bookmarks\Http\Requests;

use Ulams\Bookmarks\Models\Bookmark;
use Illuminate\Foundation\Http\FormRequest;

abstract class BookmarkRequest extends FormRequest
{
    public function getBookmark(): Bookmark
    {
        return Bookmark::findOrFail($this->route('id'));
    }
    public function rules(): array
    {
        return [
        ];
    }
}
