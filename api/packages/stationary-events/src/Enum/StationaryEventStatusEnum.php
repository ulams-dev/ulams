<?php

namespace EscolaLms\StationaryEvents\Enum;

use EscolaLms\Core\Enums\BasicEnum;

class StationaryEventStatusEnum extends BasicEnum
{
    public const DRAFT = 'draft';
    public const PUBLISHED = 'published';
    public const ARCHIVED = 'archived';
    public const PUBLISHED_UNACTIVATED = 'published_unactivated';
}
