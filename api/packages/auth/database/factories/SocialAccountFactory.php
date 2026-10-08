<?php

namespace Database\Factories\Ulams\Auth\Models;

use Ulams\Auth\Enums\SocialiteProvidersEnum;
use Ulams\Auth\Models\SocialAccount;
use Ulams\Auth\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SocialAccountFactory extends Factory
{
    protected $model = SocialAccount::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => $this->faker->randomElement(SocialiteProvidersEnum::getValues()),
            'provider_id' => $this->faker->randomNumber(6),
        ];
    }
}
