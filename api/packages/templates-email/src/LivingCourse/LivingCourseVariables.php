<?php

namespace Ulams\TemplatesEmail\LivingCourse;

use Ulams\Core\Models\User;
use Ulams\Templates\Events\EventWrapper;
use Ulams\TemplatesEmail\Core\EmailVariables;

/** Variables shared by the Living Course e-mails: the recipient and the course. */
abstract class LivingCourseVariables extends EmailVariables
{
    const VAR_USER_NAME = '@VarUserName';
    const VAR_COURSE_TITLE = '@VarCourseTitle';

    public static function mockedVariables(?User $user = null): array
    {
        return array_merge(parent::mockedVariables(), [
            self::VAR_USER_NAME => 'Alex Example',
            self::VAR_COURSE_TITLE => 'Coffee Brewing Fundamentals',
        ]);
    }

    public static function variablesFromEvent(EventWrapper $event): array
    {
        $data = $event->toArray();

        return array_merge(parent::variablesFromEvent($event), [
            self::VAR_USER_NAME => $event->getUser()->name,
            self::VAR_COURSE_TITLE => (string) ($data['courseTitle'] ?? ''),
        ]);
    }

    public static function requiredVariables(): array
    {
        return [self::VAR_USER_NAME, self::VAR_COURSE_TITLE];
    }

    public static function requiredVariablesInSection(string $sectionKey): array
    {
        return [];
    }

    public static function assignableClass(): ?string
    {
        return null;
    }
}
