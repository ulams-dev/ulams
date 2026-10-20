<?php

namespace Ulams\Compliance\Mail;

use Ulams\Templates\Events\EventWrapper;

class TrainingDueSoonVariables extends ComplianceVariables
{
    protected static function extra(EventWrapper $event): array
    {
        return [self::VAR_DUE_DATE => self::date($event->getAssignment()->due_at), self::VAR_DAYS_LEFT => self::daysUntil($event->getAssignment()->due_at)];
    }

    public static function defaultSectionsContent(): array
    {
        $values = [
                'course' => self::VAR_COURSE_TITLE,
                'user_name' => self::VAR_USER_NAME,
                'due_date' => self::VAR_DUE_DATE,
                'days_left' => self::VAR_DAYS_LEFT,
        ];

        return [
            'title' => __('Training due soon: :course', $values),
            'content' => self::wrapWithMjml(__('<h1>Hello :user_name!</h1><p>The course ":course" is due on :due_date, in :days_left days.</p>', $values)),
        ];
    }
}
