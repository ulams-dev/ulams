<?php

namespace EscolaLms\BulkNotifications\Database\Factories;

use EscolaLms\BulkNotifications\Models\BulkNotification;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BulkNotificationFactory extends Factory
{
    protected $model = BulkNotification::class;

    public function definition(): array
    {
        return [
            'channel' => 'EscolaLms\\BulkNotifications\\Channels\\' . Str::ucfirst($this->faker->word),
        ];
    }
}
