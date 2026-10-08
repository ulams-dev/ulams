<?php

namespace EscolaLms\TopicTypeGift\Http\Resources;

use EscolaLms\TopicTypeGift\Models\GiftQuiz;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *      schema="GiftQuizSimpleResource",
 *      @OA\Property(
 *          property="id",
 *          description="topic_gift_quiz_id (value used to filter quiz reports)",
 *          type="number"
 *      ),
 *      @OA\Property(
 *          property="title",
 *          description="quiz title taken from its topic",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="topic_id",
 *          type="number"
 *      ),
 * )
 *
 * @mixin GiftQuiz
 */
class GiftQuizSimpleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->topic?->title,
            'topic_id' => $this->topic?->id,
        ];
    }
}
