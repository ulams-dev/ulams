<?php

namespace Ulams\Consultations\Enum;

use Ulams\Core\Enums\BasicEnum;

class ConsultationStatusEnum extends BasicEnum
{
    public const DRAFT     = 'draft';
    public const PUBLISHED = 'published';
    public const ARCHIVED  = 'archived';
}
