<?php

namespace Ulams\TemplatesEmail\CourseAccess;

use Ulams\Core\Models\User;
use Ulams\CourseAccess\Models\CourseAccessEnquiry;
use Ulams\Templates\Events\EventWrapper;
use Ulams\TemplatesEmail\Core\EmailVariables;

abstract class CommonCourseAccessEnquiryVariables extends EmailVariables
{
    const VAR_USER_NAME       = '@VarUserName';
    const VAR_COURSE_TITLE    = '@VarCourseTitle';

    public static function mockedVariables(?User $user = null): array
    {
        $faker = \Faker\Factory::create();
        return array_merge(parent::mockedVariables(), [
            self::VAR_USER_NAME       => $faker->name(),
            self::VAR_COURSE_TITLE    => $faker->word(),
        ]);
    }

    public static function variablesFromEvent(EventWrapper $event): array
    {
        return array_merge(parent::variablesFromEvent($event), [
            self::VAR_USER_NAME    => $event->getCourseAccessEnquiry()->user->name,
            self::VAR_COURSE_TITLE => $event->getCourseAccessEnquiry()->course->title,
        ]);
    }

    public static function requiredVariables(): array
    {
        return [
            self::VAR_USER_NAME,
            self::VAR_COURSE_TITLE,
        ];
    }

    public static function requiredVariablesInSection(string $sectionKey): array
    {
        if ($sectionKey === 'content') {
            return [
                self::VAR_USER_NAME,
                self::VAR_COURSE_TITLE,
            ];
        }
        return [];
    }

    public static function assignableClass(): ?string
    {
        return CourseAccessEnquiry::class;
    }
}
