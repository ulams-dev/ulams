<?php

namespace Ulams\Identity\Services\Scim;

use Ulams\Auth\Models\Group;
use Ulams\Identity\Models\ExternalId;

/** Between a SCIM Group document (RFC 7643 4.2) and a local group with its members. */
final class GroupMapper
{
    /** Most members one request may name; larger groups are sent in several PATCH requests. */
    public const MAX_MEMBERS = 10000;

    /**
     * @param array<string,mixed> $doc
     * @return array{displayName:string,externalId:?string,members:?list<string>}
     */
    public static function parse(array $doc, bool $requireSchema): array
    {
        if ($requireSchema) {
            $schemas = $doc['schemas'] ?? null;
            if (!is_array($schemas) || !in_array(Schemas::GROUP, array_map('strval', array_filter($schemas, 'is_scalar')), true)) {
                throw ScimError::invalidSyntax('schemas must list ' . Schemas::GROUP . '.');
            }
        }
        $name = UserMapper::string($doc['displayName'] ?? null, 'displayName', 255, true);
        if ($name === null) {
            throw ScimError::invalidValue('displayName is required.');
        }

        $members = null;
        if (array_key_exists('members', $doc) && $doc['members'] !== null) {
            $list = $doc['members'];
            if (!is_array($list) || ($list !== [] && !array_is_list($list))) {
                throw ScimError::invalidValue('members must be a list.');
            }
            if (count($list) > self::MAX_MEMBERS) {
                throw ScimError::tooMany('At most ' . self::MAX_MEMBERS . ' members per request.');
            }
            $members = [];
            foreach ($list as $member) {
                if (!is_array($member) || !is_string($member['value'] ?? null) || !preg_match('/^[0-9]{1,18}$/', $member['value'])) {
                    throw ScimError::invalidValue('Every member needs a value: the id of a user of this identity provider.');
                }
                if (isset($member['type']) && (!is_string($member['type']) || strtolower($member['type']) !== 'user')) {
                    throw ScimError::invalidValue('Only users can be members; nested groups are not supported.');
                }
                $members[$member['value']] = $member['value'];
            }
            $members = array_values($members);
        }

        return [
            'displayName' => $name,
            'externalId' => UserMapper::string($doc['externalId'] ?? null, 'externalId', 255),
            'members' => $members,
        ];
    }

    /**
     * @param list<array{0:int,1:string}> $members the members this provider provisioned: id and name
     * @return array<string,mixed>
     */
    public static function toScim(Group $group, ExternalId $ext, string $base, array $members): array
    {
        $last = $group->updated_at;
        if ($ext->updated_at !== null && ($last === null || $ext->updated_at->greaterThan($last))) {
            $last = $ext->updated_at;
        }
        $resource = array_filter([
            'schemas' => [Schemas::GROUP],
            'id' => (string) $group->getKey(),
            'externalId' => $ext->external_id,
            'displayName' => $group->name,
            'members' => $members === [] ? null : array_map(fn (array $m) => ['value' => (string) $m[0], '$ref' => $base . '/Users/' . $m[0], 'type' => 'User', 'display' => $m[1]], $members),
        ], fn ($v) => $v !== null && $v !== '');
        $resource['meta'] = [
            'resourceType' => 'Group',
            'created' => $group->created_at?->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'lastModified' => $last?->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'location' => $base . '/Groups/' . $group->getKey(),
        ];
        $resource['meta']['version'] = UserMapper::version($resource);

        return $resource;
    }
}
