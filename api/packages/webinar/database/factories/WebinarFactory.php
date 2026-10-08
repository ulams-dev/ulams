<?php

namespace Ulams\Webinar\Database\Factories;

use Ulams\Auth\Models\User;
use Ulams\Core\Enums\UserRole;
use Ulams\Webinar\Enum\WebinarStatusEnum;
use Ulams\Webinar\Models\Webinar;
use Illuminate\Database\Eloquent\Factories\Factory;

class WebinarFactory extends Factory
{
    protected $model = Webinar::class;

    public function definition()
    {
        $now = now();
        return [
            'name' => $this->faker->sentence(10),
            'status' => $this->faker->randomElement(WebinarStatusEnum::getValues()),
            'description' => $this->faker->sentence,
            'active_from' => $now,
            'active_to' => (clone $now)->modify('+1 hour'),
            'yt_id' => $this->faker->word,
            'yt_url' => $this->faker->url,
            'yt_stream_url' => $this->faker->url,
            'yt_stream_key' => md5(microtime()),
        ];
    }
}
