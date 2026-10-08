<?php

namespace EscolaLms\Pages\Http\Requests;

use EscolaLms\Pages\Models\Page;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class PageCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Page::class);
    }

    public function rules(): array
    {
        return [
            'slug' => 'string|required|unique:pages',
            'title' => 'string|required',
            'content' => 'string|required',
        ];
    }

    public function getParamTitle(): string
    {
        return $this->get('title');
    }

    public function getParamSlug(): string
    {
        return $this->get('slug');
    }

    public function getParamContent(): string
    {
        return $this->get('content', '');
    }
}
