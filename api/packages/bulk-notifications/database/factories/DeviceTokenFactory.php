<?php

namespace EscolaLms\BulkNotifications\Database\Factories;

use EscolaLms\BulkNotifications\Models\DeviceToken;
use EscolaLms\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeviceTokenFactory extends Factory
{
    protected $model = DeviceToken::class;

    public function definition(): array
    {
        return [
            'token' => $this->faker->uuid,
            'user_id' => User::factory()->state(['email' => $this->faker->unique()->email]),
        ];
    }
}
