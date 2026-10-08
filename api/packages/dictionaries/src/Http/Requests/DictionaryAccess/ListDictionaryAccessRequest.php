<?php

namespace EscolaLms\Dictionaries\Http\Requests\DictionaryAccess;

use EscolaLms\Dictionaries\Models\Dictionary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ListDictionaryAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('read', $this->getDictionary());
    }

    public function rules(): array
    {
        return [];
    }

    public function getId(): int
    {
        return (int) $this->route('id');
    }

    public function getDictionary(): Dictionary
    {
        return Dictionary::findOrFail($this->getId());
    }
}
