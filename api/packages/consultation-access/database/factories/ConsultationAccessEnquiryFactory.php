<?php

namespace EscolaLms\ConsultationAccess\Database\Factories;

use EscolaLms\Auth\Models\User;
use EscolaLms\ConsultationAccess\Enum\EnquiryStatusEnum;
use EscolaLms\ConsultationAccess\Models\Consultation;
use EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiry;
use EscolaLms\Consultations\Enum\ConsultationTermStatusEnum;
use EscolaLms\Consultations\Models\ConsultationUserPivot;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ConsultationAccessEnquiryFactory extends Factory
{
    protected $model = ConsultationAccessEnquiry::class;

    public function definition(): array
    {
        $type = Str::ucfirst($this->faker->word) . $this->faker->numberBetween();

        return [
            'consultation_id' => Consultation::factory(['max_session_students' => 1]),
            'user_id' => User::factory(),
            'status' => EnquiryStatusEnum::PENDING,
            'description' => $this->faker->text(),
            'related_type' => 'EscolaLms\\' . $type . '\\Models\\' . $type,
            'related_id' => $this->faker->numberBetween(1),
        ];
    }

    public function approved(): Factory
    {
        return $this->state(function (array $attributes) {
            $consultationUser = ConsultationUserPivot::factory()
                ->create([
                    'consultation_id' => $attributes['consultation_id'],
                    'user_id' => $attributes['user_id'],
                ]);
            $userTerm = $consultationUser->userTerms()->create([
                'executed_at' => now()->modify('+2 hours')->format('Y-m-d H:i:s'),
                'executed_status' => ConsultationTermStatusEnum::APPROVED,
            ]);
            return [
                'status' => EnquiryStatusEnum::APPROVED,
                'consultation_user_id' => $consultationUser->getKey(),
                'consultation_user_term_id' => $userTerm->getKey(),
            ];
        });
    }
}
