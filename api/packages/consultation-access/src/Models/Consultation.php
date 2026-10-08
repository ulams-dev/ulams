<?php

namespace Ulams\ConsultationAccess\Models;

use Ulams\ConsultationAccess\Database\Factories\ConsultationFactory;
use Ulams\Consultations\Models\Consultation as BaseConsultation;

class Consultation extends BaseConsultation
{
    public static function newFactory(): ConsultationFactory
    {
        return ConsultationFactory::new();
    }
}
