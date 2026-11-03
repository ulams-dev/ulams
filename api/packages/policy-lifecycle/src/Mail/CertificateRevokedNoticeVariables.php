<?php

namespace Ulams\PolicyLifecycle\Mail;

use Ulams\Compliance\Mail\ComplianceVariables;
use Ulams\Templates\Events\EventWrapper;

class CertificateRevokedNoticeVariables extends ComplianceVariables
{
    const VAR_REASON = '@VarRevokedReason';

    protected static function mockedExtra(): array
    {
        return [self::VAR_REASON => 'Issued in error'];
    }

    protected static function extra(EventWrapper $event): array
    {
        return [self::VAR_REASON => $event->getReason()];
    }

    public static function defaultSectionsContent(): array
    {
        $values = ['course' => self::VAR_COURSE_TITLE, 'user_name' => self::VAR_USER_NAME, 'reason' => self::VAR_REASON];

        return [
            'title' => __('Your certificate was revoked: :course', $values),
            'content' => self::wrapWithMjml(__('<h1>Hello :user_name!</h1><p>Your certificate for ":course" was revoked. Reason: :reason</p>', $values)),
        ];
    }
}
