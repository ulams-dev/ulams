<?php

namespace Ulams\BulkNotifications\Database\Factories;

use Ulams\BulkNotifications\Models\BulkNotification;
use Ulams\BulkNotifications\Models\BulkNotificationSection;
use Illuminate\Database\Eloquent\Factories\Factory;

class BulkNotificationSectionFactory extends Factory
{
    protected $model = BulkNotificationSection::class;

    public function definition(): array
    {
        return [
            'key' => $this->faker->word,
            'value' => $this->faker->word,
            'bulk_notification_id' => BulkNotification::factory()
        ];
    }
}
