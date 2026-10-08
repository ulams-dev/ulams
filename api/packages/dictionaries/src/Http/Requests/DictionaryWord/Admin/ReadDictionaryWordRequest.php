<?php

namespace EscolaLms\Dictionaries\Http\Requests\DictionaryWord\Admin;

use EscolaLms\Dictionaries\Models\DictionaryWord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ReadDictionaryWordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('read', $this->getDictionaryWord());
    }

    public function getDictionaryWord(): DictionaryWord
    {
        return DictionaryWord::findOrFail($this->route('id'));
    }
}
