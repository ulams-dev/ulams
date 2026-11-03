<?php

namespace Ulams\Identity\Services\Scim;

use Illuminate\Support\Str;
use Ulams\Auth\Models\User;
use Ulams\Identity\Models\ExternalId;

/**
 * Between a SCIM User document (RFC 7643 4.1, enterprise extension 4.3) and the local user. Unsupported attributes
 * are ignored on input and never returned. Every string is length-capped and stripped of control characters.
 */
final class UserMapper
{
    /** Enterprise attributes kept for round-tripping (and mirrored into a model field of the same purpose when the academy has one). */
    public const ENTERPRISE_ATTRIBUTES = ['employeeNumber', 'department', 'organization', 'division', 'costCenter'];

    /** @var array<string,string> SCIM attribute => model field name */
    public const FIELD_MAP = [
        'employeeNumber' => 'employee_id',
        'department' => 'department',
        'organization' => 'organization',
        'division' => 'division',
        'costCenter' => 'cost_center',
        'title' => 'job_title',
    ];

    /**
     * @param array<string,mixed> $doc
     * @return array{userName:string,externalId:?string,given:string,family:string,displayName:?string,title:?string,email:string,active:?bool,enterprise:array<string,?string>,manager:?string,managerGiven:bool,provided:list<string>}
     */
    public static function parse(array $doc, bool $requireSchema): array
    {
        if ($requireSchema) {
            $schemas = $doc['schemas'] ?? null;
            if (!is_array($schemas) || !in_array(Schemas::USER, array_map('strval', array_filter($schemas, 'is_scalar')), true)) {
                throw ScimError::invalidSyntax('schemas must list ' . Schemas::USER . '.');
            }
        }
        $userName = self::string($doc['userName'] ?? null, 'userName', 255, true);
        if ($userName === null) {
            throw ScimError::invalidValue('userName is required.');
        }

        $name = $doc['name'] ?? [];
        if (!is_array($name) || ($name !== [] && array_is_list($name))) {
            throw ScimError::invalidValue('name must be an object.');
        }
        $given = self::string($name['givenName'] ?? null, 'name.givenName', 120) ?? '';
        $family = self::string($name['familyName'] ?? null, 'name.familyName', 120) ?? '';
        $displayName = self::string($doc['displayName'] ?? null, 'displayName', 255);
        if ($given === '' && $family === '') {
            $formatted = self::string($name['formatted'] ?? null, 'name.formatted', 240) ?? $displayName;
            if ($formatted !== null && $formatted !== '') {
                $given = Str::before($formatted, ' ');
                $family = trim(Str::contains($formatted, ' ') ? Str::after($formatted, ' ') : '');
            }
        }

        $email = self::email($doc['emails'] ?? null, $userName);

        $active = null;
        if (array_key_exists('active', $doc) && $doc['active'] !== null) {
            $active = self::boolean($doc['active'], 'active');
        }

        $extension = $doc[Schemas::ENTERPRISE] ?? [];
        if (!is_array($extension) || ($extension !== [] && array_is_list($extension))) {
            throw ScimError::invalidValue('The enterprise extension must be an object.');
        }
        $enterprise = [];
        foreach (self::ENTERPRISE_ATTRIBUTES as $attribute) {
            $enterprise[$attribute] = self::string($extension[$attribute] ?? null, $attribute, 255);
        }
        $managerGiven = array_key_exists('manager', $extension);
        $manager = null;
        if ($managerGiven && $extension['manager'] !== null) {
            $m = $extension['manager'];
            $manager = is_array($m) ? self::string($m['value'] ?? null, 'manager.value', 64) : self::string($m, 'manager', 64);
            if ($manager !== null && !preg_match('/^[0-9]{1,18}$/', $manager)) {
                throw ScimError::invalidValue('manager.value must be the id of a user of this provider.');
            }
        }

        if ($given === '') {
            $given = Str::before($email, '@');
        }

        return [
            'userName' => $userName,
            'externalId' => self::string($doc['externalId'] ?? null, 'externalId', 255),
            'given' => $given,
            'family' => $family,
            'displayName' => $displayName,
            'title' => self::string($doc['title'] ?? null, 'title', 255),
            'email' => $email,
            'active' => $active,
            'enterprise' => $enterprise,
            'manager' => $manager,
            'managerGiven' => $managerGiven,
            'provided' => array_keys($doc),
        ];
    }

    /**
     * @param array<string,mixed> $groups the user's groups at this provider: list of [id, name]
     * @return array<string,mixed>
     */
    public static function toScim(User $user, ExternalId $ext, string $base, array $groups = [], ?User $manager = null): array
    {
        $meta = (array) $ext->meta;
        $enterprise = array_filter((array) ($meta['enterprise'] ?? []), fn ($v) => $v !== null && $v !== '');
        $managerValue = $meta['manager'] ?? null;
        if ($managerValue !== null && $managerValue !== '') {
            $enterprise['manager'] = array_filter([
                'value' => (string) $managerValue,
                '$ref' => $base . '/Users/' . $managerValue,
                'displayName' => $manager !== null ? trim($manager->first_name . ' ' . $manager->last_name) : null,
            ], fn ($v) => $v !== null);
        }
        $last = $user->updated_at;
        if ($ext->updated_at !== null && ($last === null || $ext->updated_at->greaterThan($last))) {
            $last = $ext->updated_at;
        }

        $resource = array_filter([
            'schemas' => $enterprise === [] ? [Schemas::USER] : [Schemas::USER, Schemas::ENTERPRISE],
            'id' => (string) $user->getKey(),
            'externalId' => $ext->external_id,
            'userName' => $ext->user_name,
            'name' => ['formatted' => trim($user->first_name . ' ' . $user->last_name), 'givenName' => (string) $user->first_name, 'familyName' => (string) $user->last_name],
            'displayName' => ($meta['displayName'] ?? null) ?: trim($user->first_name . ' ' . $user->last_name),
            'title' => $meta['title'] ?? null,
            'emails' => $user->email === null ? null : [['value' => $user->email, 'type' => 'work', 'primary' => true]],
            'active' => (bool) $user->is_active,
            'groups' => $groups === [] ? null : array_map(fn (array $g) => ['value' => (string) $g[0], '$ref' => $base . '/Groups/' . $g[0], 'display' => $g[1]], $groups),
            Schemas::ENTERPRISE => $enterprise === [] ? null : $enterprise,
        ], fn ($v) => $v !== null && $v !== '');
        $resource['meta'] = [
            'resourceType' => 'User',
            'created' => $user->created_at?->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'lastModified' => $last?->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'location' => $base . '/Users/' . $user->getKey(),
        ];
        $resource['meta']['version'] = self::version($resource);

        return $resource;
    }

    /** A weak ETag over what the client can see: the same representation always has the same version. */
    public static function version(array $resource): string
    {
        unset($resource['meta']['version']);

        return 'W/"' . substr(hash('sha256', json_encode(self::sorted($resource), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), 0, 24) . '"';
    }

    /** @return array<string,mixed> */
    public static function sorted(array $value): array
    {
        if (!array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $k => $v) {
            if (is_array($v)) {
                $value[$k] = self::sorted($v);
            }
        }

        return $value;
    }

    public static function string(mixed $value, string $attribute, int $max, bool $required = false): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw ScimError::invalidValue("{$attribute} must be a string.");
        }
        $value = trim(preg_replace('/[\x00-\x1f\x7f]/u', '', $value) ?? '');
        if ($value === '') {
            if ($required) {
                throw ScimError::invalidValue("{$attribute} must not be empty.");
            }

            return null;
        }
        if (mb_strlen($value) > $max) {
            throw ScimError::invalidValue("{$attribute} is longer than {$max} characters.");
        }

        return $value;
    }

    /** `true`/`false`, and the strings "True"/"False" that Entra ID sends. */
    public static function boolean(mixed $value, string $attribute): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value) && in_array(strtolower($value), ['true', 'false'], true)) {
            return strtolower($value) === 'true';
        }

        throw ScimError::invalidValue("{$attribute} must be true or false.");
    }

    private static function email(mixed $emails, string $userName): string
    {
        $candidate = null;
        if ($emails !== null) {
            if (!is_array($emails) || ($emails !== [] && !array_is_list($emails))) {
                throw ScimError::invalidValue('emails must be a list.');
            }
            $first = null;
            foreach ($emails as $entry) {
                $value = is_array($entry) ? ($entry['value'] ?? null) : null;
                if (!is_string($value) || $value === '') {
                    continue;
                }
                $first ??= $value;
                if (($entry['primary'] ?? false) === true || (is_string($entry['primary'] ?? null) && strtolower($entry['primary']) === 'true')) {
                    $candidate = $value;
                    break;
                }
            }
            $candidate ??= $first;
        }
        $candidate ??= $userName;
        $email = Str::lower(trim($candidate));
        if (strlen($email) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ScimError::invalidValue($candidate === $userName && $emails === null
                ? 'Without emails the userName must be an e-mail address.'
                : 'The e-mail address is not valid or longer than 120 characters.');
        }

        return $email;
    }
}
