<?php

namespace EscolaLms\Pages\Http\Requests;

use EscolaLms\Pages\Models\Page;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class PageUpdateRequest extends FormRequest
{
    protected function prepareForValidation()
    {
        parent::prepareForValidation();
        $this->merge(['id' => $this->route('id')]);
    }

    public function authorize(): bool
    {
        $page = $this->getPage();

        return Gate::allows('update', $page);
    }

    public function rules(): array
    {
        return [
            'id' => [
                'integer',
                'required',
                Rule::exists(Page::class, 'id'),
            ],
            'slug' => ['string', Rule::unique('pages')->ignore($this->route('id'))],
            'title' => ['string'],
            'content' => ['string'],
        ];
    }

    public function getParamSlug(): string
    {
        return $this->get('slug');
    }

    public function getParamTitle(): string
    {
        return $this->get('title');
    }

    public function getParamContent(): string
    {
        return $this->get('content');
    }

    public function getParamId()
    {
        return $this->route('id');
    }

    public function getPage(): Page
    {
        return Page::findOrFail($this->route('id'));
    }
}
