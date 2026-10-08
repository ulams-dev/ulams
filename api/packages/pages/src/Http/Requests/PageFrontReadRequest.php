<?php

namespace EscolaLms\Pages\Http\Requests;

use EscolaLms\Pages\Models\Page;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PageFrontReadRequest extends FormRequest
{
    protected function prepareForValidation()
    {
        parent::prepareForValidation();
        $this->merge(['slug' => $this->route('slug')]);
    }

    public function authorize(): bool
    {
        $page = $this->getPage();

        return Gate::allows('readFront', $page);
    }

    public function rules(): array
    {
        return [
            'slug' => [
                'string',
                'required',
                Rule::exists(Page::class, 'slug'),
            ],
        ];
    }

    public function getParamSlug()
    {
        return $this->route('slug');
    }

    public function getPage(): Page
    {
        return Page::where('slug', $this->route('slug'))->firstOrFail();
    }
}
