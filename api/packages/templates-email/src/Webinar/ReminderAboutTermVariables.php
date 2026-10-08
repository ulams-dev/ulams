<?php

namespace EscolaLms\TemplatesEmail\Webinar;

class ReminderAboutTermVariables extends CommonWebinarVariables
{
    public static function defaultSectionsContent(): array
    {
        return [
            'title' => __('Remind term ":webinar"', [
                'webinar' => self::VAR_WEBINAR_TITLE,
            ]),
            'content' => self::wrapWithMjml(__('<h1>Hello :user_name!</h1><p>I would like to remind you about the upcoming webinar :webinar, which will take place :proposed_term.</p>', [
                'user_name' => self::VAR_USER_NAME,
                'webinar' => self::VAR_WEBINAR_TITLE,
                'proposed_term' => self::VAR_WEBINAR_PROPOSED_TERM
            ]),),
        ];
    }
}
