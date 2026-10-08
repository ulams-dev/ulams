<?php

namespace EscolaLms\TemplatesEmail\Tasks;

use EscolaLms\Core\Models\User;
use EscolaLms\Tasks\Models\Task;
use EscolaLms\Templates\Events\EventWrapper;
use EscolaLms\TemplatesEmail\Core\EmailVariables;

abstract class CommonTasksVariables extends EmailVariables
{
    const VAR_USER_NAME         = '@VarUserName';
    const VAR_ASSIGNEE_NAME     = '@VarAssigneeName';
    const VAR_CREATOR_NAME      = '@VarCreatorName';
    const VAR_TASK_TITLE        = '@VarTaskTitle';

    public static function mockedVariables(?User $user = null): array
    {
        $faker = \Faker\Factory::create();
        return array_merge(parent::mockedVariables(), [
            self::VAR_USER_NAME     => $faker->name(),
            self::VAR_ASSIGNEE_NAME => $faker->name(),
            self::VAR_CREATOR_NAME  => $faker->name(),
            self::VAR_TASK_TITLE    => $faker->word(),
        ]);
    }

    public static function variablesFromEvent(EventWrapper $event): array
    {
        return array_merge(parent::variablesFromEvent($event), [
            self::VAR_USER_NAME     => $event->getUser()->name,
            self::VAR_ASSIGNEE_NAME => $event->getTask()->user->name,
            self::VAR_CREATOR_NAME  => $event->getTask()->createdBy->name,
            self::VAR_TASK_TITLE    => $event->getTask()->title,
        ]);
    }

    public static function requiredVariables(): array
    {
        return [
            self::VAR_TASK_TITLE,
        ];
    }

    public static function requiredVariablesInSection(string $sectionKey): array
    {
        if ($sectionKey === 'content') {
            [
                self::VAR_TASK_TITLE,
            ];
        }
        return [];
    }

    public static function assignableClass(): ?string
    {
        return Task::class;
    }
}
