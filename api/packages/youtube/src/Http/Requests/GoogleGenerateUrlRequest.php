<?php

namespace Ulams\Youtube\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GoogleGenerateUrlRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email']
        ];
    }
}
