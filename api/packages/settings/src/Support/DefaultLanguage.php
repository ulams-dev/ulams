<?php

namespace Ulams\Settings\Support;

use Illuminate\Contracts\Validation\Validator;

/**
 * The tenant default language, the public setting `global.language`: the interface language of the account
 * pages and of courses without a language of their own (ADR 0106). The learner web app understands these codes;
 * anything else would silently fall back to English there, so the settings API refuses it.
 */
final class DefaultLanguage
{
    public const GROUP = 'global';

    public const KEY = 'language';

    public const VALUES = ['en', 'pl', 'zh'];

    public static function isTarget(?string $group, ?string $key): bool
    {
        return $group === self::GROUP && $key === self::KEY;
    }

    /**
     * Adds the rule to `value` when the request writes the default language.
     */
    public static function validate(Validator $validator, ?string $group, ?string $key): void
    {
        if (!self::isTarget($group, $key)) {
            return;
        }
        $validator->sometimes('value', ['required', 'string', 'in:' . implode(',', self::VALUES)], fn () => true);
    }
}
