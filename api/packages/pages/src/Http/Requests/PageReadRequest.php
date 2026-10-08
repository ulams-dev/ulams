<?php

namespace EscolaLms\Pages\Http\Requests;

use EscolaLms\Pages\Models\Page;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PageReadRequest extends FormRequest
{
    protected function prepareForValidation()
    {
        parent::prepareForValidation();
        $this->merge(['id' => $this->route('id')]);
    }

    public function authorize(): bool
    {
        $page = $this->getPage();

        return Gate::allows('read', $page);
    }

    public function rules(): array
    {
        return [
            'id' => [
                'integer',
                'required',
                Rule::exists(Page::class, 'id'),
            ],
        ];
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
