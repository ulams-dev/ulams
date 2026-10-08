<?php

namespace EscolaLms\Dictionaries\Http\Resources;

use EscolaLms\Dictionaries\Models\Category;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *      schema="DictionaryWordCategorySimpleResource",
 *      @OA\Property(
 *          property="id",
 *          type="integer"
 *      ),
 *      @OA\Property(
 *          property="name",
 *          type="string"
 *      ),
 *     @OA\Property(
 *          property="name_with_breadcrumbs",
 *          type="string"
 *      ),
 * )
 *
 * @mixin Category
 */
class CategorySimpleResource extends JsonResource
{
    public function toArray($request): array
    {
        $result = [
            'id' => $this->getKey(),
            'name' => $this->name,
            'name_with_breadcrumbs' => $this->name_with_breadcrumbs,
        ];

        if ($this->dictionary_words_count) {
            $result['dictionary_words_count'] = $this->dictionary_words_count;
        }

        return $result;
    }
}
