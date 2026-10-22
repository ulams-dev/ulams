<?php

namespace Ulams\Youtube\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Ulams\Settings\Enums\SettingsPermissionsEnum;

class GoogleGenerateUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(SettingsPermissionsEnum::CONFIG_UPDATE) ?? false;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email']
        ];
    }
}
