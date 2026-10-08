<?php

namespace EscolaLms\TemplatesEmail\Consultations;

class ApprovedTermVariables extends CommonConsultationVariables
{
    public static function defaultSectionsContent(): array
    {
        return [
            'title' => __('Approved term ":consultation"', [
                'consultation' => self::VAR_CONSULTATION_TITLE,
            ]),
            'content' => self::wrapWithMjml(__('<h1>Hello :user_name!</h1><p>Reported term :proposed_term for consultation ":consultation" was approved.</p>', [
                'user_name' => self::VAR_USER_NAME,
                'consultation' => self::VAR_CONSULTATION_TITLE,
                'proposed_term' => self::VAR_CONSULTATION_PROPOSED_TERM
            ])),
        ];
    }
}
