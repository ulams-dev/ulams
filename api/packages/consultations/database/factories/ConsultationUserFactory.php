<?php

namespace Ulams\Consultations\Database\Factories;

use Ulams\Consultations\Enum\ConsultationTermStatusEnum;
use Ulams\Consultations\Models\ConsultationUserPivot;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConsultationUserFactory extends Factory
{
    protected $model = ConsultationUserPivot::class;

    public function definition(): array
    {
        $now = now()->modify('+2 hours');
        return [
            'executed_at' => $now->format('Y-m-d H:i:s'),
            'executed_status' => $this->faker->randomElement(ConsultationTermStatusEnum::getValues()),
        ];
    }
}
