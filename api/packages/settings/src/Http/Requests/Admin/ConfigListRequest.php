<?php

namespace EscolaLms\Settings\Http\Requests\Admin;

use EscolaLms\Settings\Enums\SettingsPermissionsEnum;
use Illuminate\Foundation\Http\FormRequest;

class ConfigListRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user()->can(SettingsPermissionsEnum::CONFIG_LIST);
    }

    public function rules()
    {
        return [];
    }
}
