<?php

namespace Ulams\Compliance\Mail;

use Ulams\Compliance\Models\Assignment;
use Ulams\Compliance\Services\PathContext;
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
    const VAR_PATH_TITLE = '@VarPathTitle';
    const VAR_STEP_NUMBER = '@VarStepNumber';
    const VAR_STEP_COUNT = '@VarStepCount';
    const VAR_NEXT_STEP = '@VarNextStep';
    /** One ready sentence ("Step 2 of 4 of the learning path ...") in the language of the event; empty outside a path. */
    const VAR_PATH_CONTEXT = '@VarPathContext';

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
            self::VAR_PATH_TITLE => 'Onboarding',
            self::VAR_STEP_NUMBER => '2',
            self::VAR_STEP_COUNT => '4',
            self::VAR_NEXT_STEP => 'Data protection',
            self::VAR_PATH_CONTEXT => static::pathSentence(['path_title' => 'Onboarding', 'step' => 2, 'steps' => 4], null),
        ], static::mockedExtra());
    }

    public static function variablesFromEvent(EventWrapper $event): array
    {
        return array_merge(parent::variablesFromEvent($event), [
            self::VAR_USER_NAME => $event->getUser()->name,
            self::VAR_COURSE_TITLE => $event->getCourse()->title,
        ], static::pathVariables($event), static::extra($event));
    }

    /** Whether the path sentence says the step "is now open" (the assignment of an unlocked step) or only where it sits. */
    protected static function opensStep(): bool
    {
        return false;
    }

    /** The path variables of the learner's assignment; all empty when the event has none or it is not a path step. */
    private static function pathVariables(EventWrapper $event): array
    {
        $empty = [self::VAR_PATH_TITLE => '', self::VAR_STEP_NUMBER => '', self::VAR_STEP_COUNT => '', self::VAR_NEXT_STEP => '', self::VAR_PATH_CONTEXT => ''];
        $assignment = $event->toArray()['assignment'] ?? null;
        $context = $assignment instanceof Assignment ? app(PathContext::class)->forAssignment($assignment) : null;
        if ($context === null) {
            return $empty;
        }

        return [
            self::VAR_PATH_TITLE => $context['path_title'],
            self::VAR_STEP_NUMBER => (string) $context['step'],
            self::VAR_STEP_COUNT => (string) $context['steps'],
            self::VAR_NEXT_STEP => $context['next_title'],
            self::VAR_PATH_CONTEXT => static::pathSentence($context, $event->language()),
        ];
    }

    /** @param array{path_title: string, step: int, steps: int} $context */
    protected static function pathSentence(array $context, ?string $language): string
    {
        $previous = app()->getLocale();
        if ($language) {
            app()->setLocale($language);
        }
        try {
            $values = ['step' => $context['step'], 'steps' => $context['steps'], 'path' => $context['path_title']];

            return static::opensStep()
                ? __('Step :step of :steps of the learning path ":path" is now open.', $values)
                : __('This course is step :step of :steps of the learning path ":path".', $values);
        } finally {
            app()->setLocale($previous);
        }
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
