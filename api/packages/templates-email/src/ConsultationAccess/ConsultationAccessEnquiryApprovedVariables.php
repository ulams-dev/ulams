<?php

namespace EscolaLms\TemplatesEmail\ConsultationAccess;

use EscolaLms\Core\Models\User;
use EscolaLms\Templates\Events\EventWrapper;
use Illuminate\Support\Carbon;

class ConsultationAccessEnquiryApprovedVariables extends CommonConsultationAccessEnquiryVariables
{
    const VAR_APPROVED_TERM = '@VarApprovedTerm';
    const VAR_MEETING_LINK  = '@VarMeetingLink';

    public static function mockedVariables(?User $user = null): array
    {
        $faker = \Faker\Factory::create();
        return array_merge(parent::mockedVariables(), [
            self::VAR_APPROVED_TERM => $faker->dateTime->format('Y-m-d H:i'),
            self::VAR_MEETING_LINK => $faker->url,
        ]);
    }

    public static function requiredVariables(): array
    {
        return array_merge(parent::requiredVariables(), [
            self::VAR_APPROVED_TERM,
            self::VAR_MEETING_LINK,
        ]);
    }

    public static function requiredVariablesInSection(string $sectionKey): array
    {
        if ($sectionKey === 'content') {
            return array_merge(parent::requiredVariablesInSection($sectionKey), [
                self::VAR_APPROVED_TERM,
                self::VAR_MEETING_LINK,
            ]);
        }
        return [];
    }

    public static function variablesFromEvent(EventWrapper $event): array
    {
        $executedAt = $event->getConsultationAccessEnquiry()->consultationUserTerm ? $event->getConsultationAccessEnquiry()->consultationUserTerm->executed_at : $event->getConsultationAccessEnquiry()->consultationUser->executed_at;
        if (!$executedAt instanceof Carbon) {
            $executedAt = Carbon::make($executedAt);
        }
        $executedAt = $executedAt->setTimezone($event->getUser()->current_timezone)->format('Y-m-d H:i');

        return array_merge(parent::variablesFromEvent($event), [
            self::VAR_APPROVED_TERM => $executedAt,
            self::VAR_MEETING_LINK => $event->getConsultationAccessEnquiry()->meeting_link,
        ]);
    }

    public static function defaultSectionsContent(): array
    {
        return [
            'title' => __('Approved term ":consultation"', [
                'consultation' => self::VAR_CONSULTATION_NAME,
            ]),
            'content' => self::wrapWithMjml(__('<h1>Hello :user_name!</h1><p>Reported term :approved_term for consultation ":consultation" was approved. Meeting link: :link</p>', [
                'user_name' => self::VAR_USER_NAME,
                'consultation' => self::VAR_CONSULTATION_NAME,
                'approved_term' => self::VAR_APPROVED_TERM,
                'link' => self::VAR_MEETING_LINK,
            ])),
        ];
    }
}
