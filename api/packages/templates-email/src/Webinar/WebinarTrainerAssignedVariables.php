<?php

namespace EscolaLms\TemplatesEmail\Webinar;

class WebinarTrainerAssignedVariables extends CommonWebinarVariables
{
    public static function defaultSectionsContent(): array
    {
        return [
            'title' => __('Assigned for ":webinar"', [
                'webinar' => self::VAR_WEBINAR_TITLE,
            ]),
            'content' => self::wrapWithMjml(__('<h1>Hello :user_name!</h1><p>You assigned of the webinar :webinar.</p>', [
                'user_name' => self::VAR_USER_NAME,
                'webinar' => self::VAR_WEBINAR_TITLE,
            ]),),
        ];
    }
}
