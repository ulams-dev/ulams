<?php

namespace EscolaLms\Dictionaries\Http\Requests\DictionaryWord\Admin;

use EscolaLms\Dictionaries\Dtos\DictionaryWordDto;
use EscolaLms\Dictionaries\Models\DictionaryWord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * @OA\Schema(
 *      schema="CreateDictionaryWordRequest",
 *      required={"word", "dictionary_id"},
 *      @OA\Property(
 *          property="word",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="dictionary_id",
 *          type="integer"
 *      ),
 *      @OA\Property(
 *          property="description",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="data",
 *          type="object"
 *      ),
 *      @OA\Property(
 *          property="categories",
 *          description="categories",
 *          type="array",
 *          @OA\Items(
 *              type="integer",
 *          )
 *      ),
 * )
 *
 */
class CreateDictionaryWordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', DictionaryWord::class);
    }

    public function rules(): array
    {
        return [
            'word' => ['required', 'string', 'max:255'],
            'dictionary_id' => ['required', 'integer', 'exists:dictionaries,id'],
            'description' => ['nullable', 'string'],
            'data' => ['nullable', 'array'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer', 'exists:categories,id'],
        ];
    }


    public function toDto(): DictionaryWordDto
    {
        return DictionaryWordDto::instantiateFromRequest($this);
    }
}
