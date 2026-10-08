<?php

namespace Ulams\Translations\Http\Requests;

use Ulams\Translations\Models\LanguageLine;
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
