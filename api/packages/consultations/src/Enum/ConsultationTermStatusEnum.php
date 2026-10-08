<?php

namespace EscolaLms\Consultations\Enum;

use BenSampo\Enum\Enum;

class ConsultationTermStatusEnum extends Enum
{
    public const NOT_REPORTED = 'not_reported';
    public const REPORTED = 'reported';
    public const REJECT = 'reject';
    public const APPROVED = 'approved';
}
