<?php

namespace EscolaLms\Bookmarks\Http\Requests;

use EscolaLms\Bookmarks\Dtos\UpdateBookmarkDto;
use Illuminate\Support\Facades\Gate;

class UpdateBookmarkRequest extends BookmarkRequest
{
    public function authorize(): bool
    {
        return Gate::allows('updateOwn', $this->getBookmark());
    }

    public function rules(): array
    {
        return [
            'value' => ['nullable', 'string'],
            'bookmarkable_id' => ['required', 'integer'],
            'bookmarkable_type' => ['required', 'string'],
        ];
    }

    public function toDto(): UpdateBookmarkDto
    {
        return new UpdateBookmarkDto(
            $this->route('id'),
            $this->input('value'),
            $this->input('bookmarkable_type'),
            $this->input('bookmarkable_id'),
        );
    }
}
