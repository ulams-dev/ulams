<?php

namespace EscolaLms\ConsultationAccess\Models;

use EscolaLms\ConsultationAccess\Database\Factories\ConsultationFactory;
use EscolaLms\Consultations\Models\Consultation as BaseConsultation;

class Consultation extends BaseConsultation
{
    public static function newFactory(): ConsultationFactory
    {
        return ConsultationFactory::new();
    }
}
