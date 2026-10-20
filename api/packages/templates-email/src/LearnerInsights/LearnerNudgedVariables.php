<?php

namespace Ulams\TemplatesEmail\LearnerInsights;

use Ulams\Core\Models\User;
use Ulams\Templates\Events\EventWrapper;
use Ulams\TemplatesEmail\Core\EmailVariables;

/** E-mail of a Learner Insights nudge: the course, one plain-language reason and the link to continue. */
class LearnerNudgedVariables extends EmailVariables
{
    const VAR_USER_NAME = '@VarUserName';
    const VAR_COURSE_TITLE = '@VarCourseTitle';
    const VAR_REASON = '@VarReason';
    const VAR_CONTINUE_URL = '@VarContinueUrl';

    public static function mockedVariables(?User $user = null): array
    {
        return array_merge(parent::mockedVariables(), [
            self::VAR_USER_NAME => 'Alex Example',
            self::VAR_COURSE_TITLE => 'Git Basics',
            self::VAR_REASON => '7 days without activity in this course',
            self::VAR_CONTINUE_URL => 'https://learn.example.com/learn/1/2',
        ]);
    }

    public static function variablesFromEvent(EventWrapper $event): array
    {
        $data = $event->toArray();

        return array_merge(parent::variablesFromEvent($event), [
            self::VAR_USER_NAME => $event->getUser()->name,
            self::VAR_COURSE_TITLE => (string) ($data['courseTitle'] ?? ''),
            self::VAR_REASON => (string) ($data['reasonText'] ?? ''),
            self::VAR_CONTINUE_URL => (string) ($data['continueUrl'] ?? ''),
        ]);
    }

    public static function requiredVariables(): array
    {
        return [self::VAR_USER_NAME, self::VAR_COURSE_TITLE, self::VAR_CONTINUE_URL];
    }

    public static function requiredVariablesInSection(string $sectionKey): array
    {
        return [];
    }

    public static function assignableClass(): ?string
    {
        return null;
    }

    public static function defaultSectionsContent(): array
    {
        return [
            'title' => __('Continue ":course"', ['course' => self::VAR_COURSE_TITLE]),
            'content' => self::wrapWithMjml(__('<h1>Hello :user_name</h1><p>:reason. Pick up where you left off in ":course".</p><p><a href=":url">Continue</a></p><p>You get at most one reminder per course a week. You can switch reminders off in Personalisation &amp; privacy.</p>', [
                'user_name' => self::VAR_USER_NAME,
                'reason' => self::VAR_REASON,
                'course' => self::VAR_COURSE_TITLE,
                'url' => self::VAR_CONTINUE_URL,
            ])),
        ];
    }
}
