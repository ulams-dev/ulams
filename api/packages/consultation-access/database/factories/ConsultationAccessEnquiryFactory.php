<?php

namespace Ulams\ConsultationAccess\Database\Factories;

use Ulams\Auth\Models\User;
use Ulams\ConsultationAccess\Enum\EnquiryStatusEnum;
use Ulams\ConsultationAccess\Models\Consultation;
use Ulams\ConsultationAccess\Models\ConsultationAccessEnquiry;
use Ulams\Consultations\Enum\ConsultationTermStatusEnum;
use Ulams\Consultations\Models\ConsultationUserPivot;
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
            'related_type' => 'Ulams\\' . $type . '\\Models\\' . $type,
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
