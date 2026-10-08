<?php

namespace Database\Factories\Ulams\Auth\Models;

use Ulams\Auth\Models\UserSetting;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserSettingFactory extends Factory
{
    protected $model = UserSetting::class;

    public function definition(): array
    {
        return [
            'key' => Str::random(10),
            'value' => Str::random(10),
        ];
    }
}
