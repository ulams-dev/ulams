<?php

namespace Ulams\TemplatesEmail\LivingCourse;

use Ulams\Core\Models\User;
use Ulams\Templates\Events\EventWrapper;

class UpdateProposalReadyVariables extends LivingCourseVariables
{
    const VAR_ELEMENTS = '@VarElements';
    const VAR_REVIEWURL = '@VarReviewUrl';

    public static function mockedVariables(?User $user = null): array
    {
        return array_merge(parent::mockedVariables($user), [
            self::VAR_ELEMENTS => 'example',
            self::VAR_REVIEWURL => 'example',
        ]);
    }

    public static function variablesFromEvent(EventWrapper $event): array
    {
        $data = $event->toArray();
        if (isset($data['sessionId'])) {
            $data['reviewUrl'] = rtrim((string) config('course_builder.front_url'), '/') . '/studio/s/' . $data['sessionId'] . '/updates/' . ($data['proposalId'] ?? '');
        }

        return array_merge(parent::variablesFromEvent($event), [
            self::VAR_ELEMENTS => (string) ($data['elements'] ?? ''),
            self::VAR_REVIEWURL => (string) ($data['reviewUrl'] ?? ''),
        ]);
    }

    public static function defaultSectionsContent(): array
    {
        return [
            'title' => __('Update proposal ready for ":course"', ['course' => self::VAR_COURSE_TITLE]),
            'content' => self::wrapWithMjml(__('<h1>An update proposal is ready</h1><p>The source of ":course" changed. @VarElements element(s) may need an update; nothing changes in the course until you approve it.</p><p><a href="@VarReviewUrl">Review the proposal</a></p>', [
                'course' => self::VAR_COURSE_TITLE,
                'user_name' => self::VAR_USER_NAME,
            ])),
        ];
    }
}
