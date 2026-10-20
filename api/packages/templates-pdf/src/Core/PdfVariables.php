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
    const VAR_CERTIFICATE_EXPIRES_AT = '@VarCertificateExpiresAt';

    public static function mockedVariables(?User $user = null): array
    {
        $id = (string) Str::uuid();

        return [
            self::VAR_APP_NAME => config('app.name'),
            self::VAR_TODAY => today()->format('d.m.Y'),
            self::VAR_CERTIFICATE_ID => $id,
            self::VAR_CERTIFICATE_VERIFY_URL => self::verifyUrl($id),
            self::VAR_CERTIFICATE_EXPIRES_AT => today()->addYear()->format('d.m.Y'),
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

    /** Version label of what the certificate attests (e.g. the course revision); null when unknown. */
    public static function versionFromEvent(EventWrapper $event): ?string
    {
        return null;
    }

    public static function requiredSections(): array
    {
        return [];
    }

    private static function frontendUrl(): ?string
    {
        try {
            $url = \Ulams\Settings\Models\Setting::query()->where('group', 'global')->where('key', 'frontURL')->value('value');
        } catch (\Throwable) {
            return null;
        }

        return is_string($url) && $url !== '' ? rtrim($url, '/') : null;
    }

    public static function verifyUrl(string $certificateId): string
    {
        $pattern = (string) config(UlamsTemplatesPdfServiceProvider::CONFIG_KEY . '.verify_url', '{APP_URL}/certificates/verify/{id}');

        $app = rtrim((string) config('app.url'), '/');

        return strtr($pattern, [
            '{APP_URL}' => $app,
            // the learner site of the tenant (setting global.frontURL), the API host when it is not set
            '{FRONTEND_URL}' => self::frontendUrl() ?: $app,
            '{id}' => rawurlencode($certificateId),
        ]);
    }
}
