<?php

namespace EscolaLms\Pages\Http\Requests;

use EscolaLms\Pages\Models\Page;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class PageListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('list', Page::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string'],
            'slug' => ['sometimes', 'string'],
            'author_id' => ['sometimes', 'integer', 'exists:users,id'],
            'order_by' => ['sometimes', 'string', 'in:id,title,slug,author_id,active,created_at'],
            'order' => ['sometimes', 'string', 'in:ASC,DESC'],
        ];
    }
}
