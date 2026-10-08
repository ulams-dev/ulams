<?php

namespace Ulams\Settings\Http\Requests\Admin;

use Ulams\Settings\Models\Setting;
use Ulams\Settings\Enums\SettingsTypes;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class SettingsReadRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user()->can('view', Setting::class);
    }

    public function rules()
    {
        return [];
    }
}
