<?php

namespace EscolaLms\TemplatesEmail\Webinar;

class WebinarTrainerUnassignedVariables extends CommonWebinarVariables
{
    public static function defaultSectionsContent(): array
    {
        return [
            'title' => __('Unassigned from ":webinar"', [
                'webinar' => self::VAR_WEBINAR_TITLE,
            ]),
            'content' => self::wrapWithMjml(__('<h1>Hello :user_name!</h1><p>You have unassigned of the webinar :webinar.</p>', [
                'user_name' => self::VAR_USER_NAME,
                'webinar' => self::VAR_WEBINAR_TITLE,
            ]),),
        ];
    }
}
