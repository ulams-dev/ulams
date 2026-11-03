<?php

namespace Ulams\Tenancy\Support;

use Illuminate\Validation\Rule;
use Ulams\Settings\Support\ThemePresets;
use Ulams\Settings\Support\ThemeSettings;
use Ulams\Tenancy\Models\EntryPoint;

/**
 * Validation of the entry point input, shared by the tenant admin API (which also checks the target against the
 * tenant database) and the platform service (which refuses anything malformed whatever called it).
 */
final class EntryPointRules
{
    /** `/go/main` leaves an entry point and returns to the academy's own pages. */
    public const RESERVED_KEYS = ['main'];
    public const KEY_PATTERN = '/^[a-z0-9](?:[a-z0-9-]{0,38}[a-z0-9])?$/';
    public const ROLE_PATTERN = '/^[a-z0-9][a-z0-9_-]{0,48}$/';
    public const LOCALE_PATTERN = '/^[a-z]{2,3}(?:-[A-Za-z]{2,4})?$/';

    /** @return array<string, mixed> */
    public static function create(): array
    {
        return [
            'host' => ['required', 'string', 'max:253'],
            'key' => ['required', 'string', 'max:40', 'regex:' . self::KEY_PATTERN, Rule::notIn(self::RESERVED_KEYS)],
            'kind' => ['required', Rule::in(EntryPoint::KINDS)],
            'target' => ['nullable', 'array'],
            'label' => ['required', 'string', 'min:1', 'max:120'],
        ] + self::shared();
    }

    /** @return array<string, mixed> host, key and kind are fixed after creation, so they are not accepted here */
    public static function update(): array
    {
        return [
            'target' => ['sometimes', 'nullable', 'array'],
            'label' => ['sometimes', 'string', 'min:1', 'max:120'],
            'status' => ['sometimes', Rule::in([EntryPoint::ACTIVE, EntryPoint::DISABLED])],
        ] + self::shared();
    }

    /** @return array<string, mixed> */
    private static function shared(): array
    {
        return [
            'theme' => ['sometimes', 'nullable', 'array'],
            'theme.preset' => ['sometimes', 'nullable', 'string', Rule::in(ThemePresets::names())],
            'theme.accent' => ['sometimes', 'nullable', 'string', 'regex:' . ThemePresets::ACCENT_PATTERN],
            'theme.logo_alt' => ['sometimes', 'nullable', 'string', 'max:' . ThemeSettings::ALT_MAX],
            'seo' => ['sometimes', 'nullable', 'array'],
            'seo.canonical' => ['sometimes', Rule::in(['tenant', 'self'])],
            'seo.index' => ['sometimes', 'boolean'],
            'seo.title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'seo.description' => ['sometimes', 'nullable', 'string', 'max:300'],
            'locale' => ['sometimes', 'nullable', 'string', 'regex:' . self::LOCALE_PATTERN],
        ];
    }

    /**
     * Shape of the target for a kind: `{group_id}`, `{course_id}`, `{role}` or nothing for an academy.
     *
     * @param array<string, mixed>|null $target
     * @return array<string, list<string>> field errors, empty when it fits
     */
    public static function targetErrors(string $kind, ?array $target): array
    {
        $target = $target ?? [];
        $field = ['department' => 'group_id', 'course' => 'course_id', 'role' => 'role'][$kind] ?? null;
        if ($field === null) {
            return $target === [] ? [] : ['target' => ['An academy entry point has no target.']];
        }
        $extra = array_diff(array_keys($target), [$field]);
        if ($extra !== []) {
            return ['target' => ["Only \"{$field}\" is allowed in the target of a {$kind} entry point."]];
        }
        $value = $target[$field] ?? null;
        $ok = $field === 'role'
            ? is_string($value) && preg_match(self::ROLE_PATTERN, $value) === 1
            : (is_int($value) || (is_string($value) && ctype_digit($value))) && (int) $value >= 1;

        return $ok ? [] : ["target.{$field}" => $field === 'role' ? ['The role is a short key, for example manager.'] : ["target.{$field} must be a positive id."]];
    }

    /**
     * What each kind shows to search engines unless the administrator says otherwise (ADR 0195): internal
     * audiences (department, role) are not indexed.
     *
     * @return array{canonical: string, index: bool}
     */
    public static function seoDefaults(string $kind): array
    {
        return ['canonical' => 'tenant', 'index' => in_array($kind, [EntryPoint::ACADEMY, EntryPoint::COURSE], true)];
    }
}
