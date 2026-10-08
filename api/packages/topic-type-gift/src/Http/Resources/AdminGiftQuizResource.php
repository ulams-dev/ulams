<?php

namespace EscolaLms\TopicTypeGift\Http\Resources;

use EscolaLms\TopicTypeGift\Models\GiftQuiz;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *      schema="AdminGiftQuizResource",
 *      @OA\Property(
 *          property="id",
 *          description="id",
 *          type="number"
 *      ),
 *      @OA\Property(
 *          property="value",
 *          description="value",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="type",
 *          description="type",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="max_attempts",
 *          description="max attempts",
 *          type="number"
 *      ),
 *      @OA\Property(
 *          property="max_execution_time",
 *          description="max execution time",
 *          type="number"
 *      ),
 *      @OA\Property(
 *          property="min_pass_score",
 *          description="min pass score",
 *          type="number"
 *      ),
 *      @OA\Property(
 *          property="counts_to_grade",
 *          description="whether the quiz counts towards the final grade",
 *          type="boolean"
 *      ),
 *      @OA\Property(
 *          property="weight",
 *          description="weight of the grade this quiz produces in the journal (dziennik ocen)",
 *          type="integer"
 *      ),
 *      @OA\Property(
 *          property="randomize_order",
 *          description="whether the question order is randomized per attempt",
 *          type="boolean"
 *      ),
 *      @OA\Property(
 *          property="questions",
 *          description="questions",
 *          type="array",
 *          @OA\Items(ref="#/components/schemas/GiftQuestionResource")
 *      ),
 * )
 *
 */

/**
 * @mixin GiftQuiz
 */
class AdminGiftQuizResource extends JsonResource
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
            'questions' => AdminGiftQuestionResource::collection($this->questions->sortBy('order')),
        ];
    }
}
