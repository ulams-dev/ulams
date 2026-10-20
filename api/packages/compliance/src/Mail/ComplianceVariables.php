<?php

namespace Ulams\Compliance\Mail;

use Ulams\Core\Models\User;
use Ulams\Courses\Models\Course;
use Ulams\Templates\Events\EventWrapper;
use Ulams\TemplatesEmail\Core\EmailVariables;

/**
 * Variables of the compliance e-mails. Each event has its own subclass (subject, default text and
 * which extra variables it fills); admins edit the templates like any other e-mail template.
 */
abstract class ComplianceVariables extends EmailVariables
{
    const VAR_USER_NAME = '@VarUserName';
    const VAR_COURSE_TITLE = '@VarCourseTitle';
    const VAR_LEARNER_NAME = '@VarLearnerName';
    const VAR_DUE_DATE = '@VarDueDate';
    const VAR_EXPIRES_AT = '@VarExpiresAt';
    const VAR_DAYS_LEFT = '@VarDaysLeft';

    /** Extra variables this event provides besides the user and the course. */
    protected static function extra(EventWrapper $event): array
    {
        return [];
    }

    protected static function mockedExtra(): array
    {
        return [];
    }

    public static function mockedVariables(?User $user = null): array
    {
        return array_merge(parent::mockedVariables($user), [
            self::VAR_USER_NAME => 'Ada Lovelace',
            self::VAR_COURSE_TITLE => 'Safety at work',
            self::VAR_LEARNER_NAME => 'Grace Hopper',
            self::VAR_DUE_DATE => '2026-12-31',
            self::VAR_EXPIRES_AT => '2026-12-31',
            self::VAR_DAYS_LEFT => '7',
        ], static::mockedExtra());
    }

    public static function variablesFromEvent(EventWrapper $event): array
    {
        return array_merge(parent::variablesFromEvent($event), [
            self::VAR_USER_NAME => $event->getUser()->name,
            self::VAR_COURSE_TITLE => $event->getCourse()->title,
        ], static::extra($event));
    }

    public static function requiredVariables(): array
    {
        return [self::VAR_COURSE_TITLE];
    }

    public static function requiredVariablesInSection(string $sectionKey): array
    {
        return [];
    }

    public static function assignableClass(): ?string
    {
        return Course::class;
    }

    protected static function date(?\DateTimeInterface $date): string
    {
        return $date ? $date->format('Y-m-d') : '';
    }

    protected static function daysUntil(?\DateTimeInterface $date): string
    {
        return $date ? (string) max(0, (int) ceil(now()->floatDiffInDays($date, false))) : '';
    }
}
