<?php

namespace EscolaLms\TopicTypeGift\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *      schema="GiftUserSimpleResource",
 *      @OA\Property(
 *          property="id",
 *          description="id",
 *          type="number"
 *      ),
 *      @OA\Property(
 *          property="first_name",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="last_name",
 *          type="string"
 *       ),
 *      @OA\Property(
 *          property="email",
 *          description="email",
 *          type="string"
 *      ),
 * )
 *
 */
class UserSimpleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
        ];
    }
}
