<?php

namespace Ulams\Compliance\Mail;

use Ulams\Templates\Events\EventWrapper;

class TrainingAssignedVariables extends ComplianceVariables
{
    protected static function opensStep(): bool
    {
        return true;
    }

    protected static function extra(EventWrapper $event): array
    {
        return [self::VAR_DUE_DATE => self::date($event->getAssignment()->due_at)];
    }

    public static function defaultSectionsContent(): array
    {
        $values = [
                'course' => self::VAR_COURSE_TITLE,
                'user_name' => self::VAR_USER_NAME,
                'due_date' => self::VAR_DUE_DATE,
                'path_context' => self::VAR_PATH_CONTEXT,
        ];

        return [
            'title' => __('Required training: :course', $values),
            'content' => self::wrapWithMjml(__('<h1>Hello :user_name!</h1><p>You have been assigned the course ":course". :path_context Due date: :due_date.</p>', $values)),
        ];
    }
}
