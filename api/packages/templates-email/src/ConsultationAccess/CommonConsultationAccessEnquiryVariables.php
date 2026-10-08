<?php

namespace Ulams\TemplatesEmail\ConsultationAccess;

use Ulams\ConsultationAccess\Models\ConsultationAccessEnquiry;
use Ulams\Core\Models\User;
use Ulams\Templates\Events\EventWrapper;
use Ulams\TemplatesEmail\Core\EmailVariables;

abstract class CommonConsultationAccessEnquiryVariables extends EmailVariables
{
    const VAR_USER_NAME         = '@VarUserName';
    const VAR_CONSULTATION_NAME = '@VarConsultationName';

    public static function mockedVariables(?User $user = null): array
    {
        $faker = \Faker\Factory::create();
        return array_merge(parent::mockedVariables(), [
            self::VAR_USER_NAME            => $faker->name(),
            self::VAR_CONSULTATION_NAME    => $faker->word(),
        ]);
    }

    public static function variablesFromEvent(EventWrapper $event): array
    {
        return array_merge(parent::variablesFromEvent($event), [
            self::VAR_USER_NAME         => $event->getUser()->name,
            self::VAR_CONSULTATION_NAME => $event->getConsultationAccessEnquiry()->consultation->name,
        ]);
    }

    public static function requiredVariables(): array
    {
        return [
            self::VAR_USER_NAME,
            self::VAR_CONSULTATION_NAME,
        ];
    }

    public static function requiredVariablesInSection(string $sectionKey): array
    {
        if ($sectionKey === 'content') {
            return [
                self::VAR_USER_NAME,
                self::VAR_CONSULTATION_NAME,
            ];
        }
        return [];
    }

    public static function assignableClass(): ?string
    {
        return ConsultationAccessEnquiry::class;
    }
}
