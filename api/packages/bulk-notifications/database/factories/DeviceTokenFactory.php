<?php

namespace Ulams\BulkNotifications\Database\Factories;

use Ulams\BulkNotifications\Models\DeviceToken;
use Ulams\Core\Models\User;
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
