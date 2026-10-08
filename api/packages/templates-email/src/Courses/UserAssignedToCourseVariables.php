<?php

namespace EscolaLms\TemplatesEmail\Courses;

class UserAssignedToCourseVariables extends CommonUserAndCourseVariables
{
    public static function defaultSectionsContent(): array
    {
        return [
            'title' => __('You have been assigned to ":course"', [
                'course' => self::VAR_COURSE_TITLE,
            ]),
            'content' => self::wrapWithMjml(__('<h1>Hello :user_name!</h1><p>You have been assigned to course ":course".</p>', [
                'user_name' => self::VAR_USER_NAME,
                'course' => self::VAR_COURSE_TITLE,
            ])),
        ];
    }
}
