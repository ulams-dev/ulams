<?php

namespace EscolaLms\Dictionaries\Http\Requests\DictionaryWord\Admin;

use EscolaLms\Dictionaries\Models\DictionaryWord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * @OA\Schema(
 *      schema="ImportDictionaryWordRequest",
 *      required={"dictionary_id", "file"},
 *      @OA\Property(
 *          property="dictionary_id",
 *          type="integer"
 *      ),
 *      @OA\Property(
 *          property="file",
 *          type="file"
 *      ),
 * )
 *
 */
class ImportDictionaryWordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('import', DictionaryWord::class);
    }

    public function rules(): array
    {
        return [
            'dictionary_id' => ['required', 'integer', 'exists:dictionaries,id'],
            'file' => ['required', 'file', 'mimes:csv,xlsx'],
        ];
    }
}
