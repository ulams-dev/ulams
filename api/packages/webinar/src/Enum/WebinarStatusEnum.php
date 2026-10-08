<?php

namespace Ulams\Webinar\Enum;

use Ulams\Core\Enums\BasicEnum;

class WebinarStatusEnum extends BasicEnum
{
    public const DRAFT     = 'draft';
    public const PUBLISHED = 'published';
    public const ARCHIVED  = 'archived';
}
