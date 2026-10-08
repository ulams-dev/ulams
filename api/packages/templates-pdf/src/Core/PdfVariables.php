<?php

namespace Ulams\TemplatesPdf\Core;

use Illuminate\Support\Str;
use Ulams\Core\Models\User;
use Ulams\Templates\Contracts\TemplateVariableContract;
use Ulams\Templates\Core\AbstractTemplateVariableClass;
use Ulams\Templates\Events\EventWrapper;
use Ulams\TemplatesPdf\UlamsTemplatesPdfServiceProvider;

abstract class PdfVariables extends AbstractTemplateVariableClass implements TemplateVariableContract
{
    const VAR_APP_NAME       = '@VarAppName';
    const VAR_TODAY = '@VarToday';
    const VAR_CERTIFICATE_ID = '@VarCertificateId';
    const VAR_CERTIFICATE_VERIFY_URL = '@VarCertificateVerifyUrl';

    public static function mockedVariables(?User $user = null): array
    {
        $id = (string) Str::uuid();

        return [
            self::VAR_APP_NAME => config('app.name'),
            self::VAR_TODAY => today()->format('d.m.Y'),
            self::VAR_CERTIFICATE_ID => $id,
            self::VAR_CERTIFICATE_VERIFY_URL => self::verifyUrl($id),
        ];
    }

    public static function variablesFromEvent(EventWrapper $event): array
    {
        // A random id (not the row id) so verification URLs cannot be enumerated.
        $id = (string) Str::uuid();

        return [
            self::VAR_APP_NAME => config('app.name'),
            self::VAR_TODAY => today()->format('d.m.Y'),
            self::VAR_CERTIFICATE_ID => $id,
            self::VAR_CERTIFICATE_VERIFY_URL => self::verifyUrl($id),
        ];
    }

    public static function requiredSections(): array
    {
        return [];
    }

    public static function verifyUrl(string $certificateId): string
    {
        $pattern = (string) config(UlamsTemplatesPdfServiceProvider::CONFIG_KEY . '.verify_url', '{APP_URL}/certificates/verify/{id}');

        return strtr($pattern, [
            '{APP_URL}' => rtrim((string) config('app.url'), '/'),
            '{FRONTEND_URL}' => rtrim((string) config('app.frontend_url'), '/'),
            '{id}' => rawurlencode($certificateId),
        ]);
    }
}
