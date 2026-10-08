<?php

namespace EscolaLms\Consultations\Enum;

use EscolaLms\Core\Enums\BasicEnum;

class ConsultationStatusEnum extends BasicEnum
{
    public const DRAFT     = 'draft';
    public const PUBLISHED = 'published';
    public const ARCHIVED  = 'archived';
}
