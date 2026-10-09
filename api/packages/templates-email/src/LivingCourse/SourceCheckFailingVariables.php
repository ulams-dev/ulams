<?php

namespace Ulams\TemplatesEmail\LivingCourse;

use Ulams\Core\Models\User;
use Ulams\Templates\Events\EventWrapper;

class SourceCheckFailingVariables extends LivingCourseVariables
{
    const VAR_ERROR = '@VarError';

    public static function mockedVariables(?User $user = null): array
    {
        return array_merge(parent::mockedVariables($user), [
            self::VAR_ERROR => 'example',
        ]);
    }

    public static function variablesFromEvent(EventWrapper $event): array
    {
        $data = $event->toArray();
        if (isset($data['sessionId'])) {
            $data['reviewUrl'] = rtrim((string) config('course_builder.front_url'), '/') . '/studio/s/' . $data['sessionId'] . '/updates/' . ($data['proposalId'] ?? '');
        }

        return array_merge(parent::variablesFromEvent($event), [
            self::VAR_ERROR => (string) ($data['error'] ?? ''),
        ]);
    }

    public static function defaultSectionsContent(): array
    {
        return [
            'title' => __('The source of ":course" cannot be checked', ['course' => self::VAR_COURSE_TITLE]),
            'content' => self::wrapWithMjml(__('<h1>We could not check your source</h1><p>The last three checks of the source of ":course" failed: @VarError</p><p>Open the Sources page to see the details or to reconnect.</p>', [
                'course' => self::VAR_COURSE_TITLE,
                'user_name' => self::VAR_USER_NAME,
            ])),
        ];
    }
}
