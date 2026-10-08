<?php

namespace EscolaLms\TemplatesEmail\Courses;

use EscolaLms\Core\Models\User;
use EscolaLms\Courses\ValueObjects\CourseProgressCollection;
use EscolaLms\Templates\Events\EventWrapper;
use Illuminate\Support\Carbon;

class DeadlineIncomingVariables extends CommonUserAndCourseVariables
{
    const VAR_COURSE_DEADLINE = '@VarCourseDeadline';

    public static function mockedVariables(?User $user = null): array
    {
        $faker = \Faker\Factory::create();
        return array_merge(parent::mockedVariables($user), [
            self::VAR_COURSE_DEADLINE => $faker->date(),
        ]);
    }

    public static function variablesFromEvent(EventWrapper $event): array
    {
        $progress = CourseProgressCollection::make($event->getUser(), $event->getCourse());
        return array_merge(parent::variablesFromEvent($event), [
            self::VAR_COURSE_DEADLINE => Carbon::make($progress->getDeadline())
                ->setTimezone($event->getUser()->current_timezone)
                ->format('Y-m-d H:i:s')
        ]);
    }

    public static function requiredVariables(): array
    {
        return array_merge(parent::requiredVariables(), [
            self::VAR_COURSE_DEADLINE,
        ]);
    }

    public static function requiredVariablesInSection(string $sectionKey): array
    {
        if ($sectionKey === 'content') {
            [
                self::VAR_USER_NAME,
                self::VAR_COURSE_TITLE,
                self::VAR_COURSE_DEADLINE,
            ];
        }
        return [];
    }

    public static function defaultSectionsContent(): array
    {
        return [
            'title' => __('Deadline for course ":course"', [
                'course' => self::VAR_COURSE_TITLE,
            ]),
            'content' => self::wrapWithMjml(__('<h1>Hello :user_name!</h1><p>Deadline for course ":course" is coming soon. You have time until :deadline to complete this course.</p>', [
                'user_name' => self::VAR_USER_NAME,
                'course' => self::VAR_COURSE_TITLE,
                'deadline' => self::VAR_COURSE_DEADLINE
            ]),),
        ];
    }
}
