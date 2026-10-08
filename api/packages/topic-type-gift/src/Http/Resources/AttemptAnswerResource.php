<?php

namespace EscolaLms\TopicTypeGift\Http\Resources;

use EscolaLms\TopicTypeGift\Models\AttemptAnswer;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *      schema="AttemptAnswerResource",
 *      @OA\Property(
 *          property="id",
 *          description="id",
 *          type="number"
 *      ),
 *      @OA\Property(
 *          property="topic_gift_question_id",
 *          description="topic_gift_question_id",
 *          type="number"
 *      ),
 *      @OA\Property(
 *          property="answer",
 *          description="answer",
 *          type="object"
 *      ),
 *      @OA\Property(
 *          property="score",
 *          description="score",
 *          type="number"
 *      ),
 *      @OA\Property(
 *          property="feedback",
 *          description="feedback",
 *          type="string"
 *      )
 * )
 *
 */

/**
 * @mixin AttemptAnswer
 */
class AttemptAnswerResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getKey(),
            'topic_gift_question_id' => $this->topic_gift_question_id,
            'answer' => $this->answer,
            'score' => $this->attempt->isEnded() ? $this->score : null,
            'feedback' => $this->attempt->isEnded() ? $this->feedback : null,
        ];
    }
}
