<?php

namespace EscolaLms\TemplatesEmail\Courses;

class UserFinishedCourseVariables extends CommonUserAndCourseVariables
{
    public static function defaultSectionsContent(): array
    {
        return [
            'title' => __('You finished ":course"', [
                'course' => self::VAR_COURSE_TITLE,
            ]),
            'content' => self::wrapWithMjml(__('<h1>Congratulations :user_name!</h1><p>You have finished course ":course".</p>', [
                'user_name' => self::VAR_USER_NAME,
                'course' => self::VAR_COURSE_TITLE,
            ])),
        ];
    }
}
