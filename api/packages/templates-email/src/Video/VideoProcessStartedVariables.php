<?php

namespace EscolaLms\TemplatesEmail\Video;

class VideoProcessStartedVariables extends CommonVideoVariables
{
    public static function defaultSectionsContent(): array
    {
        return [
            'title' => __('Video processing progress starting for topic - :topic', [
                'topic' => self::VAR_TOPIC_TITLE
            ]),
            'content' => self::wrapWithMjml(__('<h1>Hello :user_name!</h1><p>The processing of your video has started for topic - :topic.</p>', [
                'user_name' => self::VAR_USER_NAME,
                'topic' => self::VAR_TOPIC_TITLE,
            ])),
        ];
    }
}
