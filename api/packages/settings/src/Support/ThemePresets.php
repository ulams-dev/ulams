<?php

namespace Ulams\Settings\Support;

use RuntimeException;

/**
 * The one list of tenant theme presets on the API side: `resources/themes.json`, generated from
 * `front/ui/src/theme/presets.ts` (`yarn workspace @ulams/ui theme-manifest`; CI fails when it is stale).
 * `SiteTheme`, the settings validation and the accent adjustment of the course builder read it instead of
 * keeping lists of their own.
 */
final class ThemePresets
{
    /** The value of `theme.accent`: a six digit hex colour. */
    public const ACCENT_PATTERN = '/^#[0-9a-fA-F]{6}$/';

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $presets = null;

    /** @return array<string, array<string, mixed>> the presets by name, in display order */
    public static function all(): array
    {
        if (self::$presets === null) {
            $path = __DIR__ . '/../../resources/themes.json';
            $manifest = json_decode((string) @file_get_contents($path), true);
            if (!is_array($manifest) || !is_array($manifest['presets'] ?? null)) {
                throw new RuntimeException('The theme manifest is missing or unreadable: ' . $path);
            }
            self::$presets = [];
            foreach ($manifest['presets'] as $preset) {
                self::$presets[$preset['name']] = $preset;
            }
        }

        return self::$presets;
    }

    /** @return string[] */
    public static function names(): array
    {
        return array_keys(self::all());
    }

    public static function has(mixed $name): bool
    {
        return is_string($name) && isset(self::all()[$name]);
    }

    /** The page background of a preset (what an accent has to contrast with), or null for an unknown name. */
    public static function background(string $name): ?string
    {
        return self::all()[$name]['bg'] ?? null;
    }

    public static function isAccent(mixed $value): bool
    {
        return is_string($value) && preg_match(self::ACCENT_PATTERN, $value) === 1;
    }
}
