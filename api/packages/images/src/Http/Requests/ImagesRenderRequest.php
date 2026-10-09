<?php

namespace Ulams\Images\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImagesRenderRequest extends FormRequest
{
    public const MAX_PATHS = 20;
    public const MAX_DIMENSION = 4096;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'paths' => ['required', 'array', 'min:1', 'max:' . self::MAX_PATHS],
            'paths.*' => ['required', 'array'],
            'paths.*.path' => ['required', 'string'],
            'paths.*.params' => ['sometimes', 'nullable', 'array'],
            'paths.*.params.w' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:' . self::MAX_DIMENSION],
            'paths.*.params.h' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:' . self::MAX_DIMENSION],
            'paths.*.params.size' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
