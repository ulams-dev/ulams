<?php

namespace Ulams\Settings\Http\Requests\Admin;

use Ulams\Settings\Enums\SettingsPermissionsEnum;
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
