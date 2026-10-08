<?php

namespace EscolaLms\Translations\Http\Requests;

use EscolaLms\Translations\Models\LanguageLine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ListLanguageLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('list', LanguageLine::class);
    }

    public function rules(): array
    {
        return [];
    }
}
