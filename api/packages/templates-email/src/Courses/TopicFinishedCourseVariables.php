<?php

namespace Ulams\TemplatesEmail\Courses;

use Ulams\Core\Models\User;
use Ulams\Courses\ValueObjects\CourseProgressCollection;
use Ulams\Templates\Events\EventWrapper;

class TopicFinishedCourseVariables extends CommonUserAndCourseVariables
{
    const VAR_COURSE_DEADLINE = '@VarCourseTopicFinished';

    // TODO Add variable to emails
    public static function defaultSectionsContent(): array
    {
        return [
            'title' => '',
            'content' => ''
        ];
    }
}
