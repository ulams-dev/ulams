<?php

namespace EscolaLms\Dictionaries\Http\Resources;

use EscolaLms\Dictionaries\Models\DictionaryUser;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="DictionaryAccessResource",
 *     @OA\Property(
 *          property="dictionary_id",
 *          type="integer"
 *     ),
 *     @OA\Property(
 *          property="name",
 *          type="string"
 *     ),
 *     @OA\Property(
 *          property="slug",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="end_date",
 *          type="string",
 *          format="date-time"
 *      ),
 *      @OA\Property(
 *          property="is_active",
 *          type="boolean",
 *       ),
 * )
 *
 * @mixin DictionaryUser
 */
class DictionaryAccessResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'dictionary_id' => $this->dictionary_id,
            'name' => $this->dictionary->name,
            'slug' => $this->dictionary->slug,
            'end_date' => $this->end_date,
            'is_active' => $this->is_active,
        ];
    }
}
