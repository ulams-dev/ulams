<?php

namespace EscolaLms\Translations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *      schema="retrieve-translation-request",
 *      required={"key"},
 *      @OA\Property(
 *          property="key",
 *          description="key",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="replace",
 *          description="replace",
 *          type="object"
 *      )
 * )
 *
 */

class RetrieveTranslationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'key' => ['required', 'string'],
            'replace' => ['sometimes', 'array'],
        ];
    }

    public function getKey(): string
    {
        return $this->get('key');
    }

    public function getReplace(): array
    {
        return $this->get('replace', []);
    }
}