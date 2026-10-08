<?php

namespace Ulams\Dictionaries\Http\Requests\Dictionary;

use Ulams\Dictionaries\Models\Dictionary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ReadDictionaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('read', $this->getDictionary());
    }

    public function getDictionary(): Dictionary
    {
        return Dictionary::findOrFail($this->route('id'));
    }
}
