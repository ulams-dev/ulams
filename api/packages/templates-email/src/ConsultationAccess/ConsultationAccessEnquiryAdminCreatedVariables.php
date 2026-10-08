<?php

namespace EscolaLms\TemplatesEmail\ConsultationAccess;

use EscolaLms\Core\Models\User;
use EscolaLms\Templates\Events\EventWrapper;

class ConsultationAccessEnquiryAdminCreatedVariables extends CommonConsultationAccessEnquiryVariables
{
    const VAR_STUDENT_NAME   = '@VarStudentName';
    const VAR_PROPOSED_TERMS = '@VarProposedTerms';

    public static function mockedVariables(?User $user = null): array
    {
        $faker = \Faker\Factory::create();
        return array_merge(parent::mockedVariables(), [
            self::VAR_STUDENT_NAME    => $faker->name(),
            self::VAR_PROPOSED_TERMS  => $faker->dateTime->format('Y-m-d H:i')
        ]);
    }

    public static function requiredVariables(): array
    {
        return array_merge(parent::requiredVariables(), [
            self::VAR_STUDENT_NAME,
        ]);
    }

    public static function requiredVariablesInSection(string $sectionKey): array
    {
        if ($sectionKey === 'content') {
            return array_merge(parent::requiredVariablesInSection($sectionKey), [
                self::VAR_STUDENT_NAME,
            ]);
        }
        return [];
    }

    public static function variablesFromEvent(EventWrapper $event): array
    {
        $proposedTerms = $event->getConsultationAccessEnquiry()->consultationAccessEnquiryProposedTerms
            ->pluck('proposed_at')
            ->map(fn($term) => $term->setTimezone($event->getUser()->current_timezone)->format('Y-m-d H:i'))
            ->join(', ');

        return array_merge(parent::variablesFromEvent($event), [
            self::VAR_STUDENT_NAME => $event->getConsultationAccessEnquiry()->user->name,
            self::VAR_PROPOSED_TERMS => $proposedTerms,
        ]);
    }

    public static function defaultSectionsContent(): array
    {
        return [
            'title' => __( 'New consultation access enquiry'),
            'content' => self::wrapWithMjml(__('<h1>Hello :user_name!</h1>
                           <p>:student_name has created new enquiry for access to consultation ":consultation"</p> 
                           <p>Proposed terms: :proposed_terms</p>', [
                'user_name'     => self::VAR_USER_NAME,
                'consultation'  => self::VAR_CONSULTATION_NAME,
                'student_name'  => self::VAR_STUDENT_NAME,
                'proposed_terms'=> self::VAR_PROPOSED_TERMS,
            ])),
        ];
    }
}
