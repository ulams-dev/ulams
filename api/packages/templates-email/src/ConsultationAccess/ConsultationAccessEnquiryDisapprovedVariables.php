<?php

namespace EscolaLms\TemplatesEmail\ConsultationAccess;

use EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiry;
use EscolaLms\Core\Models\User;
use EscolaLms\Templates\Events\EventWrapper;
use EscolaLms\TemplatesEmail\Core\EmailVariables;

class ConsultationAccessEnquiryDisapprovedVariables extends EmailVariables
{
    const VAR_MESSAGE           = '@VarMessage';
    const VAR_USER_NAME         = '@VarUserName';
    const VAR_CONSULTATION_NAME = '@VarConsultationName';

    public static function mockedVariables(?User $user = null): array
    {
        $faker = \Faker\Factory::create();
        return array_merge(parent::mockedVariables(), [
            self::VAR_USER_NAME            => $faker->name(),
            self::VAR_CONSULTATION_NAME    => $faker->word(),
            self::VAR_MESSAGE              => $faker->word(),
        ]);
    }
    public static function variablesFromEvent(EventWrapper $event): array
    {
        return array_merge(parent::variablesFromEvent($event), [
            self::VAR_USER_NAME         => $event->getUser()->name,
            self::VAR_CONSULTATION_NAME => $event->getConsultationName(),
            self::VAR_MESSAGE           => $event->getMessage(),
        ]);
    }

    public static function requiredVariables(): array
    {
        return [
            self::VAR_USER_NAME,
            self::VAR_CONSULTATION_NAME,
            self::VAR_MESSAGE,
        ];
    }

    public static function requiredVariablesInSection(string $sectionKey): array
    {
        if ($sectionKey === 'content') {
            return [
                self::VAR_USER_NAME,
            ];
        }
        return [];
    }

    public static function defaultSectionsContent(): array
    {
        return [
            'title' => __( 'Consultation access enquiry disapproved'),
            'content' => self::wrapWithMjml(__('<h1>Hello :user_name!</h1><p>Your enquiry for access to ":consultation" consultation has been disapproved. Reason: ":message"</p>', [
                'user_name'     => self::VAR_USER_NAME,
                'consultation'  => self::VAR_CONSULTATION_NAME,
                'message'       => self::VAR_MESSAGE,
            ])),
        ];
    }

    public static function assignableClass(): ?string
    {
        return ConsultationAccessEnquiry::class;
    }
}
