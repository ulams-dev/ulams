<?php

namespace Ulams\Consultations\Database\Factories;

use Ulams\Auth\Models\User;
use Ulams\Consultations\Enum\ConsultationStatusEnum;
use Ulams\Consultations\Models\Consultation;
use Ulams\Core\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConsultationFactory extends Factory
{
    protected $model = Consultation::class;

    public function definition()
    {
        $tutor = User::role(UserRole::TUTOR)->inRandomOrder()->first();
        $now = now();
        return [
            'name' => $this->faker->sentence(10),
            'status' => $this->faker->randomElement(ConsultationStatusEnum::getValues()),
            'description' => $this->faker->sentence,
            'short_desc' => $this->faker->sentence,
            'duration' => rand(2, 10) . " hours",
            'author_id' => empty($tutor) ? null : $tutor->getKey(),
            'active_from' => $now,
            'active_to' => (clone $now)->modify('+1 hour'),
            'max_session_students' => rand(1, 99),
        ];
    }
}
