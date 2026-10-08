<?php

namespace EscolaLms\TemplatesEmail\Youtube;

class YtProblemVariables extends CommonYoutubeVariables
{

    public static function defaultSectionsContent(): array
    {
        return [
            'title' => __('Problem with Yt integration'),
            'content' => self::wrapWithMjml(__('<h1>Hello!</h1><p>There was a problem with youtube integration. Please verify it.</p>')),
        ];
    }
}
