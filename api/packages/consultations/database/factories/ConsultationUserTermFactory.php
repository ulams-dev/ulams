<?php

namespace Ulams\Consultations\Database\Factories;

use Ulams\Consultations\Enum\ConsultationTermStatusEnum;
use Ulams\Consultations\Models\ConsultationUserTerm;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConsultationUserTermFactory extends Factory
{
    protected $model = ConsultationUserTerm::class;

    public function definition(): array
    {
        $now = now()->modify('+2 hours');
        return [
            'executed_at' => $now->format('Y-m-d H:i:s'),
            'executed_status' => $this->faker->randomElement(ConsultationTermStatusEnum::getValues()),
        ];
    }
}
