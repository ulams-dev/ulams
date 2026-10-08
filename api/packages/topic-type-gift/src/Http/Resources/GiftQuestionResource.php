<?php

namespace EscolaLms\TopicTypeGift\Http\Resources;

use EscolaLms\TopicTypeGift\Models\GiftQuestion;
use EscolaLms\TopicTypeGift\Strategies\GiftQuestionStrategyFactory;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *      schema="GiftQuestionResource",
 *      @OA\Property(
 *          property="id",
 *          description="id",
 *          type="number"
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
 *      @OA\Property(
 *          property="title",
 *          description="title",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="question",
 *          description="question",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="options",
 *          description="options",
 *          type="object"
 *      )
 * )
 *
 */

/**
 * @mixin GiftQuestion
 */
class GiftQuestionResource extends JsonResource
{
    /**
     * Base seed used to randomize the order of the question options.
     * Null keeps the options in their configured order.
     */
    private ?int $optionsSeed = null;

    public function withOptionsSeed(?int $optionsSeed): self
    {
        $this->optionsSeed = $optionsSeed;

        return $this;
    }

    public function toArray($request): array
    {
        $strategy = GiftQuestionStrategyFactory::create($this->resource, $this->optionsSeed);

        return [
            'id' => $this->id,
            'type' => $this->type,
            'score' => $this->score,
            'order' => $this->order,
            'category_id' => $this->category_id,
            'title' => $strategy->getTitle(),
            'question' => $strategy->getQuestionForStudent(),
            'options' => $strategy->getOptions(),
        ];
    }
}
