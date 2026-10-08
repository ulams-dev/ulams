<?php

namespace EscolaLms\BulkNotifications\Database\Factories;

use EscolaLms\BulkNotifications\Models\BulkNotification;
use EscolaLms\BulkNotifications\Models\BulkNotificationSection;
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
