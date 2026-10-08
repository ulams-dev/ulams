<?php

namespace Ulams\TemplatesEmail\TopicTypeProject;

use Ulams\Core\Models\User;
use Ulams\Templates\Events\EventWrapper;
use Ulams\TemplatesEmail\Core\EmailVariables;
use Ulams\TopicTypeProject\Models\ProjectSolution;

abstract class CommonProjectSolutionVariables extends EmailVariables
{
    const VAR_USER_NAME         = '@VarUserName';
    const VAR_TOPIC_TITLE       = '@VarTopicTitle';

    public static function mockedVariables(?User $user = null): array
    {
        $faker = \Faker\Factory::create();
        return array_merge(parent::mockedVariables(), [
            self::VAR_USER_NAME     => $faker->name(),
            self::VAR_TOPIC_TITLE   => $faker->word(),
        ]);
    }

    public static function variablesFromEvent(EventWrapper $event): array
    {
        return array_merge(parent::variablesFromEvent($event), [
            self::VAR_USER_NAME     => $event->getUser()->name,
            self::VAR_TOPIC_TITLE   => $event->getProjectSolution()->topic->title,
        ]);
    }

    public static function requiredVariables(): array
    {
        return [
            self::VAR_TOPIC_TITLE,
        ];
    }

    public static function requiredVariablesInSection(string $sectionKey): array
    {
        if ($sectionKey === 'content') {
            [
                self::VAR_TOPIC_TITLE,
            ];
        }
        return [];
    }

    public static function assignableClass(): ?string
    {
        return ProjectSolution::class;
    }
}
