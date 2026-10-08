<?php

namespace EscolaLms\Dictionaries\Http\Resources;

use EscolaLms\Auth\Traits\ResourceExtandable;
use EscolaLms\Dictionaries\Models\Dictionary;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *      schema="DictionaryResource",
 *      @OA\Property(
 *          property="id",
 *          type="integer"
 *      ),
 *      @OA\Property(
 *          property="name",
 *          type="string"
 *      ),
 *     @OA\Property(
 *          property="slug",
 *          type="string"
 *      ),
 *     @OA\Property(
 *          property="free_views_count",
 *          type="integer"
 *      ),
 *      @OA\Property(
 *          property="created_at",
 *          type="datetime",
 *      ),
 *      @OA\Property(
 *          property="updated_at",
 *          type="datetime",
 *      ),
 * )
 *
 * @mixin Dictionary
 */
class DictionaryResource extends JsonResource
{
    use ResourceExtandable;

    public function toArray($request): array
    {
        $fields =  [
            'id' => $this->getKey(),
            'name' => $this->name,
            'slug' => $this->slug,
            'free_views_count' => $this->free_views_count,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        return self::apply($fields, $this);
    }
}
