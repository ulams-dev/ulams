<?php

namespace Ulams\LivingCourse\Enums;

final class LivingCoursePermissionsEnum
{
    /**
     * Decide update proposals and manage source connections of any builder session of the tenant
     * (the session author always can). Seeded for the admin role; extends ADR 0027 (ADR 0030).
     */
    public const LIVING_COURSE_REVIEW = 'living_course_review';
}
