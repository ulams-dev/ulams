<?php

namespace EscolaLms\Settings\Http\Requests\Admin;

use EscolaLms\Settings\Enums\SettingsPermissionsEnum;
use Illuminate\Foundation\Http\FormRequest;

class ConfigUpdateRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user()->can(SettingsPermissionsEnum::CONFIG_UPDATE);
    }

    public function rules()
    {
        return [
            'config' => ['required', 'array'],
            'config.*.key' => ['required', 'string'],
            'config.*.value' => ['present', 'nullable']
        ];
    }
}
