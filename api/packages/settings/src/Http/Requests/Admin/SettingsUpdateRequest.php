<?php

namespace EscolaLms\Settings\Http\Requests\Admin;

use EscolaLms\Settings\Models\Setting;
use EscolaLms\Settings\Enums\SettingTypes;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class SettingsUpdateRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user()->can('update', Setting::class);
    }

    public function rules()
    {
        return [
            'key' => ['sometimes', 'string'],
            'group' => ['sometimes', 'string'],
            'public' => ['boolean'],
            'enumerable' => ['boolean'],
            'sort' => ['integer'],
            'type' => ['sometimes',  Rule::in(SettingTypes::getValues())],
            'value' => ['sometimes'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->sometimes('value', ['numeric'], fn($input) => $input->type === SettingTypes::NUMBER);
        $validator->sometimes('value', ['boolean'], fn($input) => $input->type === SettingTypes::BOOLEAN);
        $validator->sometimes('value', ['array'], fn($input) => $input->type === SettingTypes::ARRAY);
        $validator->sometimes('value', ['string'], fn($input) => !in_array($input->type, [SettingTypes::NUMBER, SettingTypes::BOOLEAN, SettingTypes::ARRAY]));
    }

    protected function passedValidation(): void
    {
        if ($this->type === SettingTypes::ARRAY) {
            $this->merge(['value' => json_encode($this->value)]);
        }
    }
}
