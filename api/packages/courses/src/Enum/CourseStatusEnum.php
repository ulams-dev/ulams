<?php

namespace Ulams\Courses\Enum;

use Ulams\Core\Enums\BasicEnum;

class CourseStatusEnum extends BasicEnum
{
    const DRAFT     = 'draft';
    const PUBLISHED = 'published';
    const ARCHIVED  = 'archived';
    const PUBLISHED_UNACTIVATED = 'published_unactivated';
}
