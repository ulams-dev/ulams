<?php

namespace EscolaLms\TemplatesEmail\Consultations;

class ChangeTermVariables extends CommonConsultationVariables
{
    public static function defaultSectionsContent(): array
    {
        return [
            'title' => __('Change term ":consultation"', [
                'consultation' => self::VAR_CONSULTATION_TITLE,
            ]),
            'content' => self::wrapWithMjml(__('<h1>Hello :user_name!</h1><p>Changed term :proposed_term for consultation ":consultation".</p>', [
                'user_name' => self::VAR_USER_NAME,
                'consultation' => self::VAR_CONSULTATION_TITLE,
                'proposed_term' => self::VAR_CONSULTATION_PROPOSED_TERM
            ])),
        ];
    }
}
