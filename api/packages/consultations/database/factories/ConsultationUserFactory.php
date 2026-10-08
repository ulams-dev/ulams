<?php

namespace EscolaLms\Consultations\Database\Factories;

use EscolaLms\Consultations\Enum\ConsultationTermStatusEnum;
use EscolaLms\Consultations\Models\ConsultationUserPivot;
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
