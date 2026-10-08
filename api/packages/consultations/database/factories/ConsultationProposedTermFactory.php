<?php

namespace EscolaLms\Consultations\Database\Factories;

use EscolaLms\Consultations\Models\Consultation;
use EscolaLms\Consultations\Models\ConsultationProposedTerm;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConsultationProposedTermFactory extends Factory
{
    protected $model = ConsultationProposedTerm::class;

    public function definition()
    {
        $consultation = Consultation::firstOrCreate();
        return [
            'consultation_id' => $consultation->getKey(),
            'proposed_at' => $this->faker->dateTimeBetween($consultation->active_from, $consultation->active_to),
        ];
    }
}
