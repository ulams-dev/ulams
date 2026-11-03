<?php

namespace Ulams\CourseVariants\Enums;

use Ulams\Core\Enums\BasicEnum;

class CourseVariantsPermissionsEnum extends BasicEnum
{
    /** Assign learners to variants, set the family's rules (lock, pin, placement, hints) and its assignment rules. */
    const ASSIGN = 'course_variants_assign';
}
