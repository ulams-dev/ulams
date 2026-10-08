<?php

namespace EscolaLms\Reports\Tests\Traits;

use Carbon\Carbon;
use EscolaLms\Core\Models\User;
use EscolaLms\Notifications\Models\DatabaseNotification;
use Faker\Factory;
use Ramsey\Uuid\Uuid;

trait NotificationTestingTrait
{
    public function createNotification(User $user, ?Carbon $date = null): DatabaseNotification
    {
        $faker = Factory::create();

        return DatabaseNotification::create([
            'id' => Uuid::uuid4(),
            'type' => $faker->word,
            'notifiable_id' => $user->getKey(),
            'notifiable_type' => 'App\Models\User',
            'data' => [],
            'event' => $faker->word,
            'created_at' => $date ?? Carbon::today()
        ]);

    }
}
