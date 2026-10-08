<?php

namespace EscolaLms\Settings\Http\Requests\Admin;

use EscolaLms\Settings\Models\Setting;
use EscolaLms\Settings\Enums\SettingsTypes;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class SettingsListRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user()->can('list', Setting::class);
    }

    public function rules()
    {
        return [];
    }
}
