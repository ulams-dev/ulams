<?php

namespace EscolaLms\Dictionaries\Http\Resources;

use EscolaLms\Dictionaries\Models\DictionaryWord;

/**
 * @OA\Schema(
 *      schema="DictionaryWordResource",
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
 *     @OA\Property(
 *          property="description",
 *          type="string"
 *      ),
 *     @OA\Property(
 *          property="data",
 *          type="object"
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
class DictionaryWordResource extends DictionaryWordSimpleResource
{
    public function toArray($request): array
    {
        return parent::toArray($request) + [
            'description' => $this->description,
            'data' => $this->data,
        ];
    }
}
