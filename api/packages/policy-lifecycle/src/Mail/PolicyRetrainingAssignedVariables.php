<?php

namespace Ulams\PolicyLifecycle\Mail;

use Ulams\Templates\Events\EventWrapper;

class PolicyRetrainingAssignedVariables extends PolicyVariables
{
    protected static function opensStep(): bool
    {
        return true;
    }

    protected static function extra(EventWrapper $event): array
    {
        return self::policyVariables($event) + [
            self::VAR_DUE_DATE => self::date($event->getAssignment()->due_at),
            self::VAR_WHAT_CHANGED => $event->getSummary(),
        ];
    }

    public static function defaultSectionsContent(): array
    {
        $values = [
            'policy' => self::VAR_POLICY_TITLE,
            'version' => self::VAR_POLICY_VERSION,
            'course' => self::VAR_COURSE_TITLE,
            'user_name' => self::VAR_USER_NAME,
            'due_date' => self::VAR_DUE_DATE,
            'what_changed' => self::VAR_WHAT_CHANGED,
            'url' => self::VAR_TRAINING_URL,
        ];

        return [
            'title' => __('The policy ":policy" changed: training required', $values),
            'content' => self::wrapWithMjml(__('<h1>Hello :user_name!</h1><p>The policy ":policy" has a new version (:version). :what_changed</p><p>Your earlier certificate no longer applies. Please complete ":course" by :due_date.</p><p><a href=":url">Open My training</a></p>', $values)),
        ];
    }
}
