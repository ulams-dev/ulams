<?php

namespace Ulams\Compliance\Mail;

use Ulams\Core\Models\User;
use Ulams\Templates\Events\EventWrapper;
use Ulams\TemplatesEmail\Core\EmailVariables;

/** Variables of the learning-path e-mails. */
abstract class PathVariables extends EmailVariables
{
    const VAR_USER_NAME = '@VarUserName';
    const VAR_PATH_TITLE = '@VarPathTitle';
    const VAR_DUE_DATE = '@VarDueDate';

    public static function mockedVariables(?User $user = null): array
    {
        return array_merge(parent::mockedVariables($user), [
            self::VAR_USER_NAME => 'Ada Lovelace',
            self::VAR_PATH_TITLE => 'Onboarding',
            self::VAR_DUE_DATE => '2026-12-31',
        ]);
    }

    public static function variablesFromEvent(EventWrapper $event): array
    {
        $enrollment = $event->getEnrollment();

        return array_merge(parent::variablesFromEvent($event), [
            self::VAR_USER_NAME => $event->getUser()->name,
            self::VAR_PATH_TITLE => $enrollment->path?->title ?? '',
            self::VAR_DUE_DATE => $enrollment->due_at ? $enrollment->due_at->format('Y-m-d') : '',
        ]);
    }

    public static function requiredVariables(): array
    {
        return [self::VAR_PATH_TITLE];
    }

    public static function requiredVariablesInSection(string $sectionKey): array
    {
        return [];
    }

    public static function assignableClass(): ?string
    {
        return null;
    }

    protected static function content(string $title, string $body): array
    {
        $values = ['user_name' => self::VAR_USER_NAME, 'path' => self::VAR_PATH_TITLE, 'due_date' => self::VAR_DUE_DATE];

        return ['title' => __($title, $values), 'content' => self::wrapWithMjml(__($body, $values))];
    }
}
