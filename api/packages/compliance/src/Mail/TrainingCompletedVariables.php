<?php

namespace Ulams\Compliance\Mail;

use Ulams\Templates\Events\EventWrapper;

class TrainingCompletedVariables extends ComplianceVariables
{
    const VAR_CERTIFICATE_URL = '@VarCertificateUrl';

    protected static function mockedExtra(): array
    {
        return [self::VAR_CERTIFICATE_URL => rtrim((string) config('app.frontend_url'), '/') . '/account/training'];
    }

    protected static function extra(EventWrapper $event): array
    {
        return [self::VAR_CERTIFICATE_URL => self::certificateUrl($event->getAssignment()->certificate_id)];
    }

    /**
     * The certificate's verification page when it is already linked to the assignment, otherwise "My training",
     * where the certificate shows up as soon as it is issued (it is created a moment after the course closes).
     */
    private static function certificateUrl(?int $certificateId): string
    {
        $base = rtrim((string) config('app.frontend_url'), '/');

        return $certificateId ? $base . '/certificates/verify/' . $certificateId : $base . '/account/training';
    }

    public static function defaultSectionsContent(): array
    {
        $values = [
                'course' => self::VAR_COURSE_TITLE,
                'user_name' => self::VAR_USER_NAME,
                'path_context' => self::VAR_PATH_CONTEXT,
                'certificate_url' => self::VAR_CERTIFICATE_URL,
        ];

        return [
            'title' => __('Training completed: :course', $values),
            'content' => self::wrapWithMjml(__('<h1>Well done :user_name!</h1><p>You have completed the required course ":course". :path_context</p><p><a href=":certificate_url">Your training record and certificate</a></p>', $values)),
        ];
    }
}
