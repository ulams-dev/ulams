<?php

namespace Ulams\Compliance\Mail;

use Ulams\Templates\Events\EventWrapper;

class CertificateExpiredVariables extends ComplianceVariables
{
    protected static function extra(EventWrapper $event): array
    {
        return [self::VAR_EXPIRES_AT => self::date($event->getCertificate()->expires_at)];
    }

    public static function defaultSectionsContent(): array
    {
        $values = [
                'course' => self::VAR_COURSE_TITLE,
                'user_name' => self::VAR_USER_NAME,
                'expires_at' => self::VAR_EXPIRES_AT,
        ];

        return [
            'title' => __('Your certificate has expired: :course', $values),
            'content' => self::wrapWithMjml(__('<h1>Hello :user_name!</h1><p>Your certificate for ":course" expired on :expires_at. Please renew it.</p>', $values)),
        ];
    }
}
