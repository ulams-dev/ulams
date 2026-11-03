<?php

namespace Ulams\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Ulams\Auth\Rules\AllowedReturnUrl;

class InitProfileDeletionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('delete', $this->user());
    }

    public function rules(): array
    {
        return [
            'return_url' => ['required', 'string', 'max:2048', new AllowedReturnUrl()],
        ];
    }

    public function getReturnUrl(): ?string
    {
        return $this->input('return_url');
    }
}
