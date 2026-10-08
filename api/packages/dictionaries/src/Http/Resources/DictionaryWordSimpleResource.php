<?php

namespace EscolaLms\Dictionaries\Http\Resources;

use EscolaLms\Dictionaries\Models\DictionaryWord;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *      schema="DictionaryWordSimpleResource",
 *      @OA\Property(
 *          property="id",
 *          type="integer"
 *      ),
 *      @OA\Property(
 *          property="word",
 *          type="string"
 *      ),
 *     @OA\Property(
 *          property="dictionary_id",
 *          type="integer"
 *      ),
 *      @OA\Property(
 *          property="categories",
 *          type="array",
 *          @OA\Items(ref="#/components/schemas/DictionaryWordCategorySimpleResource")
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
 * @mixin DictionaryWord
 */
class DictionaryWordSimpleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getKey(),
            'word' => $this->word,
            'dictionary_id' => $this->dictionary_id,
            'categories' => CategorySimpleResource::collection($this->categories),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
