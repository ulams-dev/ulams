<?php

namespace EscolaLms\Dictionaries\Http\Requests\DictionaryWord\Admin;

use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\Dictionaries\Dtos\DictionaryWordCriteriaDto;
use EscolaLms\Dictionaries\Dtos\PageDto;
use EscolaLms\Dictionaries\Models\DictionaryWord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ListDictionaryWordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('list', DictionaryWord::class);
    }

    public function rules(): array
    {
        return [
            'order_by' => ['sometimes', 'string', 'in:id,word,created_at,updated_at'],
            'order' => ['sometimes', 'string', 'in:ASC,DESC'],
            'page' => ['sometimes', 'integer'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['integer'],
        ];
    }

    public function getCriteria(): DictionaryWordCriteriaDto
    {
        return DictionaryWordCriteriaDto::instantiateFromRequest($this);
    }

    public function getPage(): PageDto
    {
        return PageDto::instantiateFromRequest($this);
    }

    public function getOrder(): OrderDto
    {
        return OrderDto::instantiateFromRequest($this);
    }
}
