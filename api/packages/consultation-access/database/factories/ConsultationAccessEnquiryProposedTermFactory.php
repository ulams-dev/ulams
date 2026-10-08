<?php

namespace EscolaLms\ConsultationAccess\Database\Factories;

use EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiry;
use EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiryProposedTerm;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConsultationAccessEnquiryProposedTermFactory extends Factory
{
    protected $model = ConsultationAccessEnquiryProposedTerm::class;

    public function definition(): array
    {
        return [
            'consultation_access_enquiry_id' => ConsultationAccessEnquiry::factory(),
            'proposed_at' => $this->faker->dateTimeBetween(),
        ];
    }
}
