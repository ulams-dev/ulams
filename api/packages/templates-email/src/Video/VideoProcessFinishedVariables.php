<?php

namespace EscolaLms\TemplatesEmail\Video;

class VideoProcessFinishedVariables extends CommonVideoVariables
{
    public static function defaultSectionsContent(): array
    {
        return [
            'title' => __('Video processing finished for topic - :topic', [
                'topic' => self::VAR_TOPIC_TITLE
            ]),
            'content' => self::wrapWithMjml(__('<h1>Hello :user_name!</h1><p>Your video in topic :topic has been processed successfully!</p>', [
                'user_name' => self::VAR_USER_NAME,
                'topic' => self::VAR_TOPIC_TITLE,
            ])),
        ];
    }
}
