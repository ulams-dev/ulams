<?php

namespace Ulams\ConsultationAccess\Database\Factories;

use Ulams\ConsultationAccess\Models\ConsultationAccessEnquiry;
use Ulams\ConsultationAccess\Models\ConsultationAccessEnquiryProposedTerm;
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
