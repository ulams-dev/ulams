<?php

namespace Ulams\Auth\Support;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Scope vocabulary and the route map of scoped personal access tokens (ADR 0074).
 *
 * A scope is `<area>:read` or `<area>:write` (write implies read) or `*`. Scopes only narrow what
 * the user's own permissions allow; they never grant anything.
 */
final class TokenScopes
{
    public const AREAS = [
        'courses', 'users', 'enrolments', 'settings', 'events', 'certificates', 'commerce',
        'reports', 'lti', 'builder', 'living-course', 'learner', 'tokens', 'platform',
    ];

    /** @var array<string,list<string>> */
    public const PRESETS = [
        'read-only' => [
            'courses:read', 'users:read', 'enrolments:read', 'settings:read', 'events:read', 'certificates:read',
            'commerce:read', 'reports:read', 'lti:read', 'builder:read', 'living-course:read', 'learner:read', 'tokens:read',
        ],
        'author' => ['courses:write', 'builder:write', 'living-course:write', 'reports:read'],
        'admin' => ['*'],
        'learner' => ['learner:write'],
        'ci' => ['courses:write', 'builder:write', 'living-course:write'],
    ];

    /** @var list<array{0:string,1:string,2?:array<string,mixed>}>|null */
    private static ?array $map = null;

    /** @return list<string> every valid scope, `*` last */
    public static function all(): array
    {
        $scopes = [];
        foreach (self::AREAS as $area) {
            $scopes[] = $area . ':read';
            $scopes[] = $area . ':write';
        }
        $scopes[] = '*';

        return $scopes;
    }

    /** @return array<string,list<string>> */
    public static function presets(): array
    {
        return self::PRESETS;
    }

    /**
     * Validate, de-duplicate and sort. `*` absorbs everything else; a preset name (`@author`) expands.
     *
     * @param  array<int,mixed>  $scopes
     * @return list<string>
     *
     * @throws InvalidArgumentException on an unknown scope or an empty list
     */
    public static function normalize(array $scopes): array
    {
        $out = [];
        foreach ($scopes as $scope) {
            if (!is_string($scope)) {
                throw new InvalidArgumentException('A scope must be a string.');
            }
            $scope = trim($scope);
            if (str_starts_with($scope, '@')) {
                $preset = self::PRESETS[substr($scope, 1)] ?? throw new InvalidArgumentException("Unknown scope preset {$scope}.");
                array_push($out, ...$preset);
                continue;
            }
            if (!in_array($scope, self::all(), true)) {
                throw new InvalidArgumentException("Unknown scope {$scope}.");
            }
            $out[] = $scope;
        }
        $out = array_values(array_unique($out));
        if ($out === []) {
            throw new InvalidArgumentException('A token needs at least one scope.');
        }
        if (in_array('*', $out, true)) {
            return ['*'];
        }
        sort($out);

        return $out;
    }

    /** Does the granted list let a request needing `$area` at the given level through? */
    public static function allows(array $granted, string $area, bool $write): bool
    {
        if (in_array('*', $granted, true) || in_array($area . ':write', $granted, true)) {
            return true;
        }

        return !$write && in_array($area . ':read', $granted, true);
    }

    /** True when every scope in `$requested` is already covered by `$granted` (no escalation). */
    public static function covers(array $granted, array $requested): bool
    {
        foreach ($requested as $scope) {
            if ($scope === '*') {
                if (!in_array('*', $granted, true)) {
                    return false;
                }
                continue;
            }
            [$area, $level] = explode(':', $scope, 2) + [1 => 'read'];
            if (!self::allows($granted, $area, $level === 'write')) {
                return false;
            }
        }

        return true;
    }

    /** Does the list carry an explicit `platform:*` scope? (`*` alone does not count.) */
    public static function hasPlatformScope(array $scopes): bool
    {
        foreach ($scopes as $scope) {
            if (str_starts_with($scope, 'platform:')) {
                return true;
            }
        }

        return false;
    }

    public static function isPlatformHost(?string $host = null): bool
    {
        $host ??= request()->getHost();

        return in_array(strtolower($host), array_map('strtolower', (array) config('ulams_tenancy.platform_hosts', [])), true);
    }

    /** Longest lifetime of a new token: 365 days, 30 on a platform host. */
    public static function maxDays(?string $host = null): int
    {
        return self::isPlatformHost($host) ? 30 : 365;
    }

    /** @return list<array{0:string,1:string,2?:array<string,mixed>}> */
    public static function map(): array
    {
        return self::$map ??= require __DIR__ . '/../../resources/token-scopes.php';
    }

    /**
     * @return array{area:string,write:bool}|null null: the route is not in the map (deny)
     */
    public static function requirement(string $uri, string $method): ?array
    {
        $method = strtoupper($method);
        foreach (self::map() as $entry) {
            [$pattern, $area] = $entry;
            $options = $entry[2] ?? [];
            if (!Str::is($pattern, ltrim($uri, '/'))) {
                continue;
            }
            if (isset($options['methods']) && !in_array($method, $options['methods'], true)) {
                continue;
            }
            $write = match ($options['level'] ?? null) {
                'read' => false,
                'write' => true,
                default => !in_array($method, ['GET', 'HEAD', 'OPTIONS'], true),
            };

            return ['area' => $area, 'write' => $write];
        }

        return null;
    }

    /** @return list<string> every scope registered with Passport so personal access tokens can carry it */
    public static function passportScopes(): array
    {
        return array_values(array_diff(self::all(), ['*']));
    }

    /** Areas whose reads are audited as data access (users, reports). */
    public static function auditsReads(string $area): bool
    {
        return in_array($area, ['users', 'reports'], true);
    }
}
