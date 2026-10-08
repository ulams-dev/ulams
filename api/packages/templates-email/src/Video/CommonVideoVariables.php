<?php

namespace EscolaLms\TemplatesEmail\Video;

use EscolaLms\Core\Models\User;
use EscolaLms\Templates\Events\EventWrapper;
use EscolaLms\TemplatesEmail\Core\EmailVariables;

abstract class CommonVideoVariables extends EmailVariables
{

    const VAR_USER_NAME = '@VarUserName';

    const VAR_TOPIC_TITLE = '@VarTopicTitle';

    public static function mockedVariables(?User $user = null): array
    {
        $faker = \Faker\Factory::create();
        return array_merge(parent::mockedVariables($user), [
            self::VAR_USER_NAME => $faker->word,
            self::VAR_TOPIC_TITLE => $faker->word,
        ]);
    }

    public static function variablesFromEvent(EventWrapper $event): array
    {
        return array_merge(parent::variablesFromEvent($event), [
            self::VAR_USER_NAME => $event->getUser()->name,
            self::VAR_TOPIC_TITLE => $event->getTopic()->title,
        ]);
    }

    public static function defaultSectionsContent(): array
    {
        return [
            'title' => '',
            'content' => ''
        ];
    }

    public static function assignableClass(): ?string
    {
        return null;
    }

    public static function requiredVariables(): array
    {
        return [
            self::VAR_TOPIC_TITLE
        ];
    }

    public static function requiredVariablesInSection(string $sectionKey): array
    {
        if ($sectionKey === 'content') {
            return [
                self::VAR_TOPIC_TITLE,
            ];
        }

        return [];
    }
}
