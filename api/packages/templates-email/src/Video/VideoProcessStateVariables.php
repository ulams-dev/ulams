<?php

namespace EscolaLms\TemplatesEmail\Video;

use EscolaLms\Core\Models\User;
use EscolaLms\Templates\Events\EventWrapper;

class VideoProcessStateVariables extends CommonVideoVariables
{
    const VAR_VIDEO_PERCENTAGE = '@VarVideoPercentage';

    public static function mockedVariables(?User $user = null): array
    {
        $faker = \Faker\Factory::create();
        return array_merge(parent::mockedVariables($user), [
            self::VAR_VIDEO_PERCENTAGE => $faker->numberBetween(0, 100),
        ]);
    }

    public static function variablesFromEvent(EventWrapper $event): array
    {
        return array_merge(parent::variablesFromEvent($event), [
            self::VAR_VIDEO_PERCENTAGE => $event->getPercentage(),
        ]);
    }

    public static function defaultSectionsContent(): array
    {
        return [
            'title' => __('Video processing progress update for topic - :topic - :percentage%', [
                'topic' => self::VAR_TOPIC_TITLE,
                'percentage' => self::VAR_VIDEO_PERCENTAGE,
            ]),
            'content' => self::wrapWithMjml(__('<h1>Hello :user_name!</h1><p>The progress of processing your video in :topic is currently :percentage%.</p>', [
                'user_name' => self::VAR_USER_NAME,
                'percentage' => self::VAR_VIDEO_PERCENTAGE,
                'topic' => self::VAR_TOPIC_TITLE,
            ])),
        ];
    }
}
