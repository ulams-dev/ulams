<?php

namespace EscolaLms\TemplatesEmail\Courses;

class UserUnassignedFromCourseVariables extends CommonUserAndCourseVariables
{
    public static function defaultSectionsContent(): array
    {
        return [
            'title' => __('You have been unassigned from ":course"', [
                'course' => self::VAR_COURSE_TITLE,
            ]),
            'content' =>  self::wrapWithMjml(__('<h1>Hello :user_name!</h1><p>You have been unassigned from course ":course".</p>', [
                'user_name' => self::VAR_USER_NAME,
                'course' => self::VAR_COURSE_TITLE,
            ]))
        ];
    }
}
