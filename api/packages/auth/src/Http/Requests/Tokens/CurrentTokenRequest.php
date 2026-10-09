<?php

namespace Ulams\Auth\Http\Requests\Tokens;

use Illuminate\Foundation\Http\FormRequest;

class CurrentTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [];
    }
}
