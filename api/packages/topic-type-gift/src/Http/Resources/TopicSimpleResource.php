<?php

namespace EscolaLms\TopicTypeGift\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *      schema="GiftTopicSimpleResource",
 *      @OA\Property(
 *          property="id",
 *          type="number"
 *      ),
 *      @OA\Property(
 *          property="title",
 *          type="string"
 *      ),
 * )
 *
 */
class TopicSimpleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
        ];
    }
}
