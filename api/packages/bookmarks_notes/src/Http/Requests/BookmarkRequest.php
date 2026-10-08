<?php

namespace EscolaLms\Bookmarks\Http\Requests;

use EscolaLms\Bookmarks\Models\Bookmark;
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
