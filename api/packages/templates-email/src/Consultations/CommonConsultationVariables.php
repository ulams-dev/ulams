<?php

namespace EscolaLms\TemplatesEmail\Consultations;

use Carbon\Carbon;
use EscolaLms\Consultations\Models\ConsultationUserPivot;
use EscolaLms\Consultations\Services\Contracts\ConsultationServiceContract;
use EscolaLms\Core\Models\User;
use EscolaLms\Templates\Events\EventWrapper;
use EscolaLms\TemplatesEmail\Core\EmailVariables;

abstract class CommonConsultationVariables extends EmailVariables
{
    const VAR_USER_NAME       = '@VarUserName';
    const VAR_CONSULTATION_TITLE    = '@VarConsultationTitle';
    const VAR_CONSULTATION_PROPOSED_TERM    = '@VarConsultationProposedTerm';
    const VAR_CONSULTATION_DATE_TIME_START = '@VarConsultationDateTimeStart';
    const VAR_CONSULTATION_DATE_TIME_END = '@VarConsultationDateTimeEnd';

    public static function mockedVariables(?User $user = null): array
    {
        $faker = \Faker\Factory::create();
        $date = $faker->dateTime();
        return array_merge(parent::mockedVariables(), [
            self::VAR_USER_NAME       => $faker->name(),
            self::VAR_CONSULTATION_TITLE    => $faker->word(),
            self::VAR_CONSULTATION_PROPOSED_TERM => $date->format('Y-m-d H:i:s'),
            self::VAR_CONSULTATION_DATE_TIME_START => Carbon::parse($date)->format('Ymd\THis'),
            self::VAR_CONSULTATION_DATE_TIME_END => Carbon::parse($date)->addHour()->format('Ymd\THis'),
        ]);
    }

    public static function variablesFromEvent(EventWrapper $event): array
    {
        $executedAt = $event->getConsultationUserTerm() ? $event->getConsultationUserTerm()->executed_at : ($event->getConsultationTerm()->executed_at ?? '');
        if ($executedAt) {
            if (!$executedAt instanceof Carbon) {
                $executedAt = Carbon::make($executedAt);
            }
            $executedAt = $executedAt
                ->setTimezone($event->getUser()->current_timezone);

        }
        $executedTo = $executedAt ? app(ConsultationServiceContract::class)
            ->generateDateTo($executedAt, $event->getConsultationTerm()->consultation->getDuration()) : $executedAt;

        return array_merge(parent::variablesFromEvent($event), [
            self::VAR_USER_NAME    => $event->getUser()->name,
            self::VAR_CONSULTATION_TITLE => $event->getConsultationTerm()->consultation->name,
            self::VAR_CONSULTATION_PROPOSED_TERM => $executedAt->format('Y-m-d H:i:s'),
            self::VAR_CONSULTATION_DATE_TIME_START => $executedAt->format('Ymd\THisp'),
            self::VAR_CONSULTATION_DATE_TIME_END => $executedTo->format('Ymd\THisp'),
        ]);
    }

    public static function requiredVariables(): array
    {
        return [
            self::VAR_USER_NAME,
            self::VAR_CONSULTATION_TITLE,
            self::VAR_CONSULTATION_PROPOSED_TERM,
        ];
    }

    public static function requiredVariablesInSection(string $sectionKey): array
    {
        if ($sectionKey === 'content') {
            return [
                self::VAR_USER_NAME,
                self::VAR_CONSULTATION_TITLE,
                self::VAR_CONSULTATION_PROPOSED_TERM,
            ];
        }
        return [];
    }

    public static function assignableClass(): ?string
    {
        return ConsultationUserPivot::class;
    }
}
