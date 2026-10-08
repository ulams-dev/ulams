<?php

namespace EscolaLms\CourseAccess\Enum;

use EscolaLms\Core\Enums\BasicEnum;

class CourseAccessPermissionEnum extends BasicEnum
{
    const CREATE_OWN_COURSE_ACCESS_ENQUIRY = 'course-access_create-own';
    const DELETE_OWN_COURSE_ACCESS_ENQUIRY = 'course-access_delete-own';
    const LIST_OWN_COURSE_ACCESS_ENQUIRY = 'course-access_list-own';

    const LIST_COURSE_ACCESS_ENQUIRY = 'course-access_list';
    const DELETE_COURSE_ACCESS_ENQUIRY = 'course-access_delete';
    const APPROVE_COURSE_ACCESS_ENQUIRY = 'course-access_approve';
}
