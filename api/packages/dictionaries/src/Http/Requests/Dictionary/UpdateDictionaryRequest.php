<?php

namespace EscolaLms\Dictionaries\Http\Requests\Dictionary;

use EscolaLms\Dictionaries\Dtos\DictionaryDto;
use EscolaLms\Dictionaries\Models\Dictionary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * @OA\Schema(
 *      schema="UpdateDictionaryRequest",
 *      required={"name"},
 *      @OA\Property(
 *          property="name",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="free_views_count",
 *          type="integer"
 *      )
 * )
 *
 */
class UpdateDictionaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->getDictionary());
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'free_views_count' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function getId(): int
    {
        return (int) $this->route('id');
    }

    public function getDictionary(): Dictionary
    {
        return Dictionary::findOrFail($this->getId());
    }

    public function toDto(): DictionaryDto
    {
        return DictionaryDto::instantiateFromRequest($this);
    }
}
