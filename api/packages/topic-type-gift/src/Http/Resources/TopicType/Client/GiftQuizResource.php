<?php

namespace EscolaLms\TopicTypeGift\Http\Resources\TopicType\Client;

use EscolaLms\Courses\Http\Resources\TopicType\Contracts\TopicTypeResourceContract;
use EscolaLms\TopicTypeGift\Models\GiftQuiz;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GiftQuiz
 */
class GiftQuizResource extends JsonResource implements TopicTypeResourceContract
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'value' => $this->value,
            'max_attempts' => $this->max_attempts,
            'max_execution_time' => $this->max_execution_time,
            'min_pass_score' => $this->min_pass_score,
            'counts_to_grade' => $this->counts_to_grade,
            'weight' => $this->weight,
            'randomize_order' => $this->randomize_order,
        ];
    }
}
