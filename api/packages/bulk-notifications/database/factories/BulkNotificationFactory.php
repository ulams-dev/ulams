<?php

namespace Ulams\BulkNotifications\Database\Factories;

use Ulams\BulkNotifications\Models\BulkNotification;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BulkNotificationFactory extends Factory
{
    protected $model = BulkNotification::class;

    public function definition(): array
    {
        return [
            'channel' => 'Ulams\\BulkNotifications\\Channels\\' . Str::ucfirst($this->faker->word),
        ];
    }
}
