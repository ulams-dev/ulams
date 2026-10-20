<?php

namespace Ulams\Compliance\Mail;

use Ulams\Templates\Events\EventWrapper;

class TrainingOverdueVariables extends ComplianceVariables
{
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
        ];

        return [
            'title' => __('Overdue training: :course', $values),
            'content' => self::wrapWithMjml(__('<h1>Hello :user_name!</h1><p>The course ":course" was due on :due_date and is not finished yet. Please complete it as soon as possible.</p>', $values)),
        ];
    }
}
