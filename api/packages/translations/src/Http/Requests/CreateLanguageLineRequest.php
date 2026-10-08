<?php

namespace Ulams\Translations\Http\Requests;

use Ulams\Translations\Models\LanguageLine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CreateLanguageLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', LanguageLine::class);
    }

    public function rules(): array
    {
        return [
            'group' => ['required', 'string', 'max:255'],
            'key' => ['required', 'string', 'max:255', 'unique:language_lines,key,NULL,id,group,' . $this->input('group')],
            'text' => ['required', 'array', 'min:1'],
            'text.*' => ['required', 'string', 'max:255'],
            'public' => ['boolean'],
        ];
    }
}
