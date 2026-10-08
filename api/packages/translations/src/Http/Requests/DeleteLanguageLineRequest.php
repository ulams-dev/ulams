<?php

namespace Ulams\Translations\Http\Requests;

use Ulams\Translations\Models\LanguageLine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DeleteLanguageLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('delete', $this->getLanguageLine());
    }

    public function rules(): array
    {
        return [];
    }

    public function getLanguageLine(): LanguageLine
    {
        return LanguageLine::findOrFail($this->route('id'));
    }
}
