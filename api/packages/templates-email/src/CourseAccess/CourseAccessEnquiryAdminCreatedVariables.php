<?php

namespace EscolaLms\TemplatesEmail\CourseAccess;

class CourseAccessEnquiryAdminCreatedVariables extends CommonCourseAccessEnquiryVariables
{
    public static function defaultSectionsContent(): array
    {
        return [
            'title' => __('New course access enquiry'),
            'content' => self::wrapWithMjml(__('<h1>Hello!</h1><p>Student :user_name has enquired about access to the ":course".</p>', [
                'user_name' => self::VAR_USER_NAME,
                'course' => self::VAR_COURSE_TITLE,
            ])),
        ];
    }
}
