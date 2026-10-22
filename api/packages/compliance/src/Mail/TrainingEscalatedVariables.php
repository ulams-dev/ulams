<?php

namespace Ulams\Compliance\Mail;

use Ulams\Templates\Events\EventWrapper;

class TrainingEscalatedVariables extends ComplianceVariables
{
    protected static function extra(EventWrapper $event): array
    {
        return [self::VAR_LEARNER_NAME => $event->getLearner()->name, self::VAR_DUE_DATE => self::date($event->getAssignment()->due_at)];
    }

    public static function defaultSectionsContent(): array
    {
        $values = [
                'learner_name' => self::VAR_LEARNER_NAME,
                'course' => self::VAR_COURSE_TITLE,
                'user_name' => self::VAR_USER_NAME,
                'due_date' => self::VAR_DUE_DATE,
                'path_context' => self::VAR_PATH_CONTEXT,
        ];

        return [
            'title' => __('Overdue training of :learner_name: :course', $values),
            'content' => self::wrapWithMjml(__('<h1>Hello :user_name!</h1><p>:learner_name has not completed the required course ":course", due on :due_date. :path_context</p>', $values)),
        ];
    }
}
