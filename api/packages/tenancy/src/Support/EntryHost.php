<?php

namespace Ulams\Tenancy\Support;

use Ulams\Tenancy\Models\EntryPoint;
use Ulams\Tenancy\Models\Tenant;

/**
 * Rules for the host of an entry point (ADR 0195): a plain lower case DNS name, never an address, never a
 * platform or content host, never one that another tenant or entry point already owns.
 */
final class EntryHost
{
    private const LABEL = '/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/';

    /** Lower case, no surrounding space, no trailing dot. */
    public static function normalize(string $host): string
    {
        return rtrim(strtolower(trim($host)), '.');
    }

    /** @return string|null the reason the host is not acceptable as a name, null when it is */
    public static function syntaxError(string $host): ?string
    {
        if ($host === '' || strlen($host) > 253) {
            return 'The host must be a DNS name of at most 253 characters.';
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false || str_contains($host, ':') || str_contains($host, '/') || str_contains($host, '@')) {
            return 'Give the host name only: no address, port, path or credentials.';
        }
        $labels = explode('.', $host);
        if (count($labels) < 2) {
            return 'The host needs at least two labels, for example sales.example.org.';
        }
        foreach ($labels as $label) {
            if (!preg_match(self::LABEL, $label)) {
                return 'Each label of the host is 1 to 63 lower case letters, digits or hyphens, not starting or ending with a hyphen.';
            }
        }
        if (ctype_digit(end($labels))) {
            return 'The last label of the host cannot be numeric.';
        }
        // the content origin serves untrusted packages: it never doubles as an academy host
        if (in_array('content', $labels, true)) {
            return 'A host with a "content" label is reserved for the content origin.';
        }

        return null;
    }

    /** @return string|null the reason the host is taken or reserved, null when it is free */
    public static function conflict(string $host, ?string $ignoreEntryId = null): ?string
    {
        if (in_array($host, (array) config('ulams_tenancy.platform_hosts', []), true)) {
            return 'That host belongs to the platform.';
        }
        $tenantHost = Tenant::query()->where('api_host', $host)->orWhere('front_host', $host)->orWhere('admin_host', $host)->exists();
        if ($tenantHost) {
            return 'That host is already used by an academy.';
        }
        foreach (Tenant::query()->pluck('slug') as $slug) {
            if (TenantNaming::pattern('content_host', $slug) === $host) {
                return 'That host is the content origin of an academy.';
            }
        }
        $taken = EntryPoint::query()->where('host', $host)
            ->when($ignoreEntryId !== null, fn ($q) => $q->where('id', '!=', $ignoreEntryId))->exists();

        return $taken ? 'That host is already an entry point.' : null;
    }

    /** The platform suffix the host sits under, or null for a custom domain. */
    public static function platformSuffix(string $host): ?string
    {
        return self::under($host, (array) config('ulams_tenancy.entry_platform_suffixes', []));
    }

    public static function isTrustedSuffix(string $host): bool
    {
        return self::under($host, (array) config('ulams_tenancy.entry_trusted_suffixes', [])) !== null;
    }

    /** A platform host is `{slug}-<name>` directly under its suffix (one label, which wildcard certificates cover). */
    public static function platformFlatnessError(string $host, string $suffix, string $slug): ?string
    {
        $label = substr($host, 0, -strlen($suffix));
        if (str_contains($label, '.') || !str_starts_with($label, $slug . '-') || strlen($label) <= strlen($slug) + 1) {
            return "Hosts under {$suffix} must be one label that starts with \"{$slug}-\", for example {$slug}-sales{$suffix}.";
        }

        return null;
    }

    /** @param list<string> $suffixes */
    private static function under(string $host, array $suffixes): ?string
    {
        foreach ($suffixes as $suffix) {
            $suffix = '.' . ltrim((string) $suffix, '.');
            if ($suffix !== '.' && str_ends_with($host, $suffix) && strlen($host) > strlen($suffix)) {
                return $suffix;
            }
        }

        return null;
    }
}
