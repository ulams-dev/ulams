<?php

namespace EscolaLms\TopicTypeGift\Http\Resources\TopicType\Export;

use EscolaLms\TopicTypeGift\Http\Resources\AdminGiftQuestionResource;
use EscolaLms\TopicTypeGift\Models\GiftQuiz;
use EscolaLms\TopicTypes\Http\Resources\TopicType\Contacts\TopicTypeResourceContract;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GiftQuiz
 */
class GiftQuizResource extends JsonResource implements TopicTypeResourceContract
{
    public function toArray($request): array
    {
        return [
            'value' => $this->value,
            'max_attempts' => $this->max_attempts,
            'max_execution_time' => $this->max_execution_time,
            'min_pass_score' => $this->min_pass_score,
            'counts_to_grade' => $this->counts_to_grade,
            'weight' => $this->weight,
            'randomize_order' => $this->randomize_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'questions' => AdminGiftQuestionResource::collection($this->questions->sortBy('order')),
        ];
    }
}
