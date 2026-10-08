<?php

namespace EscolaLms\Translations\Http\Requests;

use EscolaLms\Translations\Models\LanguageLine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateLanguageLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->getLanguageLine());
    }

    public function rules(): array
    {
        return [
            'group' => [
                'sometimes',
                'string',
                'max:255',
            ],
            'key' => [
                'sometimes',
                'string',
                'max:255',
                'unique:language_lines,key,' . $this->route('id') . ',id,group,' . $this->input('group'),
            ],
            'text' => [
                'sometimes',
                'array',
                'min:1',
            ],
            'text.*' => [
                'sometimes',
                'string',
                'max:255',
            ],
            'public' => ['boolean'],
        ];
    }

    public function getLanguageLine(): LanguageLine
    {
        return LanguageLine::findOrFail($this->route('id'));
    }
}
