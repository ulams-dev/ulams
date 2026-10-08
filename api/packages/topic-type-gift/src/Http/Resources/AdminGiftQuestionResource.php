<?php

namespace EscolaLms\TopicTypeGift\Http\Resources;

use EscolaLms\TopicTypeGift\Models\GiftQuestion;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *      schema="AdminGiftQuestionResource",
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
 *          property="score",
 *          description="score",
 *          type="number"
 *      ),
 *      @OA\Property(
 *          property="order",
 *          description="order",
 *          type="number"
 *      ),
 *      @OA\Property(
 *          property="category_id",
 *          description="category_id",
 *          type="integer"
 *      ),
 * )
 *
 */

/**
 * @mixin GiftQuestion
 */
class AdminGiftQuestionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'value' => $this->value,
            'type' => $this->type,
            'score' => $this->score,
            'order' => $this->order,
            'category_id' => $this->category_id,
        ];
    }
}
