<?php

namespace Ulams\Compliance\Mail;

use Ulams\Templates\Events\EventWrapper;

class TrainingCompletedForManagerVariables extends ComplianceVariables
{
    protected static function extra(EventWrapper $event): array
    {
        return [self::VAR_LEARNER_NAME => $event->getLearner()->name];
    }

    public static function defaultSectionsContent(): array
    {
        $values = [
                'learner_name' => self::VAR_LEARNER_NAME,
                'course' => self::VAR_COURSE_TITLE,
                'user_name' => self::VAR_USER_NAME,
                'path_context' => self::VAR_PATH_CONTEXT,
        ];

        return [
            'title' => __('Training completed by :learner_name: :course', $values),
            'content' => self::wrapWithMjml(__('<h1>Hello :user_name!</h1><p>:learner_name has completed the required course ":course". :path_context</p>', $values)),
        ];
    }
}
