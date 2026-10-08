<?php

namespace EscolaLms\Dictionaries\Http\Requests\DictionaryWord\Admin;

use EscolaLms\Dictionaries\Models\DictionaryWord;
use Illuminate\Support\Facades\Gate;

class UpdateDictionaryWordRequest extends CreateDictionaryWordRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->getDictionaryWord());
    }

    public function getId(): int
    {
        return (int) $this->route('id');
    }

    public function getDictionaryWord(): DictionaryWord
    {
        return DictionaryWord::findOrFail($this->getId());
    }
}
