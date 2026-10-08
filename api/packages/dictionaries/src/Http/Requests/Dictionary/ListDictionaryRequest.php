<?php

namespace EscolaLms\Dictionaries\Http\Requests\Dictionary;

use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\Dictionaries\Dtos\DictionaryCriteriaDto;
use EscolaLms\Dictionaries\Dtos\PageDto;
use EscolaLms\Dictionaries\Models\Dictionary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ListDictionaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('list', Dictionary::class);
    }

    public function rules(): array
    {
        return [
            'order_by' => ['sometimes', 'string', 'in:id,name,slug,created_at,updated_at'],
            'order' => ['sometimes', 'string', 'in:ASC,DESC'],
            'page' => ['sometimes', 'integer'],
        ];
    }

    public function getCriteria(): DictionaryCriteriaDto
    {
        return DictionaryCriteriaDto::instantiateFromRequest($this);
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
