<?php

namespace Ulams\Auth\Http\Requests\Admin;

use Ulams\Auth\Enums\SettingStatusEnum;
use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Auth\Http\Requests\ExtendableRequest;
use Ulams\Auth\Models\Group;
use Ulams\Auth\Models\User;
use Ulams\Auth\Rules\NoHtmlTags;
use Ulams\ModelFields\Facades\ModelFields;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rule;

class UserCreateRequest extends ExtendableRequest
{
    public function authorize()
    {
        return $this->user()->can('create', User::class);
    }

    public function rules()
    {
        $rules = [
            'first_name' => ['required', 'string', 'max:255', new NoHtmlTags()],
            'last_name' => ['required', 'string', 'max:255', new NoHtmlTags()],
            'roles' => ['sometimes', 'array'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'verified' => ['sometimes', 'boolean'],
            'password' => User::PASSWORD_RULES,
            'groups' => ['sometimes', 'array'],
            'groups.*' => ['integer', Rule::exists((new Group())->getTable(), (new Group())->getKeyName())],
            'settings' => [
                'sometimes',
                'array'
            ],
            'settings.*' => [
                'array'
            ],
            'settings.*.key' => [
                'required',
                'string',
            ],
            'settings.*.value' => [
                'required',
                'nullable',
                'string',
            ],
            'return_url' => ['url', Rule::requiredIf(fn () => !Config::get(UlamsAuthServiceProvider::CONFIG_KEY . '.return_url'))],
        ];

        return array_merge($rules, ModelFields::getFieldsMetadataRules(User::class));
    }
}
