<?php

namespace Ulams\TemplatesEmail\LivingCourse;

use Ulams\Core\Models\User;
use Ulams\Templates\Events\EventWrapper;

class CourseContentUpdatedVariables extends LivingCourseVariables
{

    public static function mockedVariables(?User $user = null): array
    {
        return array_merge(parent::mockedVariables($user), [
        ]);
    }

    public static function variablesFromEvent(EventWrapper $event): array
    {
        $data = $event->toArray();
        if (isset($data['sessionId'])) {
            $data['reviewUrl'] = rtrim((string) config('course_builder.front_url'), '/') . '/studio/s/' . $data['sessionId'] . '/updates/' . ($data['proposalId'] ?? '');
        }

        return array_merge(parent::variablesFromEvent($event), [
        ]);
    }

    public static function defaultSectionsContent(): array
    {
        return [
            'title' => __('":course" was updated', ['course' => self::VAR_COURSE_TITLE]),
            'content' => self::wrapWithMjml(__('<h1>Hello :user_name</h1><p>":course" was updated. Your progress and your results stay as they are; open the course to see what changed.</p>', [
                'course' => self::VAR_COURSE_TITLE,
                'user_name' => self::VAR_USER_NAME,
            ])),
        ];
    }
}
