<?php

namespace EscolaLms\Translations\Http\Requests;

use EscolaLms\Translations\Dto\PublicTranslationListCriteriaDto;
use EscolaLms\Translations\Enum\ConstantEnum;
use Illuminate\Foundation\Http\FormRequest;

class PublicListLanguageLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }

    public function getCriteria(): PublicTranslationListCriteriaDto
    {
        return PublicTranslationListCriteriaDto::instantiateFromRequest($this);
    }

    public function getPagination(): int
    {
        return $this->get('per_page') ?? ConstantEnum::PER_PAGE;
    }
}
