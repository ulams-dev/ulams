<?php

namespace EscolaLms\TemplatesEmail\Consultations;

use Carbon\Carbon;
use EscolaLms\Core\Models\User;
use EscolaLms\Templates\Events\EventWrapper;

class ReportTermVariables extends CommonConsultationVariables
{
    const VAR_CONSULTATION_BUYER_NAME    = '@VarConsultationBuyerName';

    public static function mockedVariables(?User $user = null): array
    {
        $faker = \Faker\Factory::create();
        return array_merge(parent::mockedVariables(), [
            self::VAR_CONSULTATION_BUYER_NAME => $faker->name,
        ]);
    }

    public static function requiredVariables(): array
    {
        return array_merge(parent::requiredVariables(), [
            self::VAR_CONSULTATION_BUYER_NAME,
        ]);
    }

    public static function requiredVariablesInSection(string $sectionKey): array
    {
        if ($sectionKey === 'content') {
            return array_merge(parent::requiredVariablesInSection($sectionKey), [
                self::VAR_CONSULTATION_BUYER_NAME,
            ]);
        }
        return [];
    }

    public static function variablesFromEvent(EventWrapper $event): array
    {
        return array_merge(parent::variablesFromEvent($event), [
            self::VAR_CONSULTATION_BUYER_NAME => $event->getConsultationTerm()->user->name,
        ]);
    }

    public static function defaultSectionsContent(): array
    {
        return [
            'title' => __('Report term ":consultation"', [
                'consultation' => self::VAR_CONSULTATION_TITLE,
            ]),
            'content' => self::wrapWithMjml(__('<h1>Hello :user_name!</h1><p>Buyer :consultation_buyer_name consultation ":consultation" reported proposed term realization: :proposed_term.</p>', [
                'user_name' => self::VAR_USER_NAME,
                'consultation_buyer_name' => self::VAR_CONSULTATION_BUYER_NAME,
                'consultation' => self::VAR_CONSULTATION_TITLE,
                'proposed_term' => self::VAR_CONSULTATION_PROPOSED_TERM
            ]),),
        ];
    }
}
