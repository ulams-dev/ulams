<?php

namespace EscolaLms\TopicTypeGift\Http\Requests\Admin;

use EscolaLms\TopicTypeGift\Dtos\Criteria\ExportQuestionsCriteriaDto;
use EscolaLms\TopicTypeGift\Enum\TopicTypeGiftPermissionEnum;
use Illuminate\Foundation\Http\FormRequest;

class AdminExportGiftQuestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(TopicTypeGiftPermissionEnum::EXPORT_GIFT_QUIZ_QUESTION);
    }

    public function rules(): array
    {
        return [
            'topic_gift_quiz_id' => ['sometimes', 'integer'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['sometimes', 'integer'],
            'ids' => ['sometimes', 'array'],
            'ids.*' => ['sometimes', 'integer'],
        ];
    }

    public function toDto(): ExportQuestionsCriteriaDto
    {
        return ExportQuestionsCriteriaDto::instantiateFromRequest($this);
    }
}
