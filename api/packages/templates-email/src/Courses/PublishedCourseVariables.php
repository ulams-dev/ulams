<?php

namespace EscolaLms\TemplatesEmail\Courses;

use EscolaLms\Core\Models\User;
use EscolaLms\Courses\ValueObjects\CourseProgressCollection;
use EscolaLms\Templates\Events\EventWrapper;

class PublishedCourseVariables extends CommonUserAndCourseVariables
{
    const VAR_COURSE_DEADLINE = '@VarCoursePublished';

    // TODO Add variable to emails
    public static function defaultSectionsContent(): array
    {
        return [
            'title' => '',
            'content' => ''
        ];
    }
}
