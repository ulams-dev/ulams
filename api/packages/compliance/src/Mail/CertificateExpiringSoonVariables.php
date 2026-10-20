<?php

namespace Ulams\Compliance\Mail;

use Ulams\Templates\Events\EventWrapper;

class CertificateExpiringSoonVariables extends ComplianceVariables
{
    protected static function extra(EventWrapper $event): array
    {
        return [self::VAR_EXPIRES_AT => self::date($event->getCertificate()->expires_at), self::VAR_DAYS_LEFT => self::daysUntil($event->getCertificate()->expires_at)];
    }

    public static function defaultSectionsContent(): array
    {
        $values = [
                'course' => self::VAR_COURSE_TITLE,
                'user_name' => self::VAR_USER_NAME,
                'expires_at' => self::VAR_EXPIRES_AT,
                'days_left' => self::VAR_DAYS_LEFT,
        ];

        return [
            'title' => __('Your certificate expires soon: :course', $values),
            'content' => self::wrapWithMjml(__('<h1>Hello :user_name!</h1><p>Your certificate for ":course" expires on :expires_at, in :days_left days.</p>', $values)),
        ];
    }
}
