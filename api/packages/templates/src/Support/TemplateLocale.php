<?php

namespace Ulams\Templates\Support;

use Ulams\Settings\Models\Setting;

/**
 * Which language a template is sent in (ADR 0106 order): the language of the course (or of any model the
 * event carries with a language), then the tenant default language (the public setting `global.language`),
 * then English, then the template that has no language (every template before per-language templates).
 */
final class TemplateLocale
{
    /** Languages the admin offers a template for. A template may carry any other two or three letter code. */
    public const SUPPORTED = ['en', 'pl', 'zh'];

    public const FALLBACK = 'en';

    /**
     * `pl`, `pl-PL`, `pl_PL` and ` PL ` are all `pl`; anything that is not a language code is null.
     */
    public static function normalize(?string $code): ?string
    {
        $language = strtolower(trim((string) $code));
        $language = preg_split('/[-_]/', $language)[0] ?? '';

        return preg_match('/^[a-z]{2,3}$/', $language) ? $language : null;
    }

    public static function tenantDefault(): ?string
    {
        return self::normalize(
            Setting::query()->where('group', 'global')->where('key', 'language')->first()?->value
        );
    }

    /**
     * The languages to try, best first. `null` stands for the template without a language and is last.
     *
     * @return array<int, string|null>
     */
    public static function fallbackChain(?string $eventLanguage): array
    {
        $chain = [];
        foreach ([self::normalize($eventLanguage), self::tenantDefault(), self::FALLBACK] as $locale) {
            if ($locale !== null && !in_array($locale, $chain, true)) {
                $chain[] = $locale;
            }
        }
        $chain[] = null;

        return $chain;
    }
}
