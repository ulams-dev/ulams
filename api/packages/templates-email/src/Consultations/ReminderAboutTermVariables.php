<?php

namespace EscolaLms\TemplatesEmail\Consultations;

use EscolaLms\Consultations\Services\Contracts\ConsultationServiceContract;
use EscolaLms\Templates\Events\EventWrapper;
use Illuminate\Support\Facades\Log;

class ReminderAboutTermVariables extends CommonConsultationVariables
{
    const VAR_CONSULTATION_TERM_ID = '@VarConsultationTermId';
    const VAR_JITSI_URL = '@VarJitsiUrl';

    public static function requiredVariables(): array
    {
        return array_merge(parent::requiredVariables(), [
            self::VAR_CONSULTATION_TERM_ID,
        ]);
    }

    public static function requiredVariablesInSection(string $sectionKey): array
    {
        if ($sectionKey === 'content') {
            return array_merge(parent::requiredVariablesInSection($sectionKey), [
                self::VAR_CONSULTATION_TERM_ID,
            ]);
        }
        return [];
    }

    public static function variablesFromEvent(EventWrapper $event): array
    {
        $consultationService = app(ConsultationServiceContract::class);
        $executedAt = $event->getConsultationUserTerm() ?? ($event->getConsultationTerm()->executed_at ?? '');
        return array_merge(parent::variablesFromEvent($event), [
            self::VAR_CONSULTATION_TERM_ID => $event->getConsultationTerm()->user->name,
            self::VAR_JITSI_URL => $consultationService->generateJitsiUrlForEmail($event->getConsultationTerm()->getKey(), $event->getConsultationTerm()->user->getKey(), $executedAt),
        ]);
    }

    public static function defaultSectionsContent(): array
    {
        return [
            'title' => __('Remind term ":consultation"', [
                'consultation' => self::VAR_CONSULTATION_TITLE,
            ]),
            'content' => self::wrapWithMjml(__('<h1>Hello :user_name!</h1><p>I would like to remind you about the upcoming consultation :consultation, which will take place :proposed_term.</p>', [
                'user_name' => self::VAR_USER_NAME,
                'consultation' => self::VAR_CONSULTATION_TITLE,
                'consultation_term_id' => self::VAR_CONSULTATION_TERM_ID,
                'proposed_term' => self::VAR_CONSULTATION_PROPOSED_TERM
            ]),),
        ];
    }
}
