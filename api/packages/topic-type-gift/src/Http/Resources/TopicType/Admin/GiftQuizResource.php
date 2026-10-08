<?php

namespace Ulams\TopicTypeGift\Http\Resources\TopicType\Admin;

use Ulams\Courses\Http\Resources\TopicType\Contracts\TopicTypeResourceContract;
use Ulams\TopicTypeGift\Http\Resources\AdminGiftQuestionResource;
use Ulams\TopicTypeGift\Models\GiftQuiz;
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
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'questions' => AdminGiftQuestionResource::collection($this->questions->sortBy('order')),
        ];
    }
}
