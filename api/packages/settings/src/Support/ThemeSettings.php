<?php

namespace Ulams\Settings\Support;

use Illuminate\Contracts\Validation\Validator;

/**
 * Validation of the tenant theme settings written through the generic settings endpoints: the group `theme`
 * holds `theme` (a preset of the shared list), `accent` (a six digit hex colour) and `logoAlt` (the logo's
 * text alternative). `theme.logo` is written only by the logo endpoint, which checks the file; the generic
 * endpoints refuse it so a setting cannot point the learner header at any address.
 */
final class ThemeSettings
{
    public const GROUP = 'theme';

    public const LOGO_KEY = 'logo';

    public const ALT_MAX = 120;

    public static function isTarget(?string $group, ?string $key): bool
    {
        return $group === self::GROUP && in_array($key, ['theme', 'accent', 'logoAlt', self::LOGO_KEY], true);
    }

    public static function validate(Validator $validator, ?string $group, ?string $key): void
    {
        if (!self::isTarget($group, $key)) {
            return;
        }
        $rule = match ($key) {
            'theme' => ['required', 'string', 'in:' . implode(',', ThemePresets::names())],
            'accent' => ['required', 'string', 'regex:' . ThemePresets::ACCENT_PATTERN],
            'logoAlt' => ['nullable', 'string', 'max:' . self::ALT_MAX],
            default => null,
        };
        if ($rule === null) {
            $validator->after(fn (Validator $v) => $v->errors()->add('value', __('The logo is set by uploading a file to /api/admin/theme/logo.')));

            return;
        }
        $validator->sometimes('value', $rule, fn () => true);
    }
}
