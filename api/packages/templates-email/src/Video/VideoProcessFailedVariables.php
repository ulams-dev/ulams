<?php

namespace EscolaLms\TemplatesEmail\Video;

class VideoProcessFailedVariables extends CommonVideoVariables
{
    public static function defaultSectionsContent(): array
    {
        return [
            'title' => __('Video processing progress failed for topic - :topic', [
                'topic' => self::VAR_TOPIC_TITLE
            ]),
            'content' => self::wrapWithMjml(__('<h1>Hello :user_name!</h1><p>Your video in topic :topic was not processed successfully.</p>', [
                'user_name' => self::VAR_USER_NAME,
                'topic' => self::VAR_TOPIC_TITLE,
            ])),
        ];
    }
}
