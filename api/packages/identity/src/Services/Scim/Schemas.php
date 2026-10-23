<?php

namespace Ulams\Identity\Services\Scim;

/**
 * URNs and the discovery documents of the SCIM server (RFC 7643 5, 6, 7 and RFC 7644 4): ServiceProviderConfig,
 * ResourceTypes and Schemas. They describe exactly what the server implements; attributes it ignores are not listed.
 */
final class Schemas
{
    public const USER = 'urn:ietf:params:scim:schemas:core:2.0:User';
    public const ENTERPRISE = 'urn:ietf:params:scim:schemas:extension:enterprise:2.0:User';
    public const GROUP = 'urn:ietf:params:scim:schemas:core:2.0:Group';
    public const LIST = 'urn:ietf:params:scim:api:messages:2.0:ListResponse';
    public const SEARCH = 'urn:ietf:params:scim:api:messages:2.0:SearchRequest';
    public const CONFIG = 'urn:ietf:params:scim:schemas:core:2.0:ServiceProviderConfig';
    public const RESOURCE_TYPE = 'urn:ietf:params:scim:schemas:core:2.0:ResourceType';
    public const SCHEMA = 'urn:ietf:params:scim:schemas:core:2.0:Schema';

    /** @return array<string,mixed> */
    public static function serviceProviderConfig(string $base, ?string $docs): array
    {
        return array_filter([
            'schemas' => [self::CONFIG],
            'documentationUri' => $docs ?: null,
            'patch' => ['supported' => true],
            'bulk' => ['supported' => false, 'maxOperations' => 0, 'maxPayloadSize' => 0],
            'filter' => ['supported' => true, 'maxResults' => (int) config('ulams_identity.scim.max_count')],
            'changePassword' => ['supported' => false],
            'sort' => ['supported' => false],
            'etag' => ['supported' => true],
            'authenticationSchemes' => [array_filter([
                'type' => 'oauthbearertoken',
                'name' => 'OAuth Bearer Token',
                'description' => 'A SCIM token created for this identity provider, sent as Authorization: Bearer <token>.',
                'specUri' => 'https://www.rfc-editor.org/info/rfc6750',
                'documentationUri' => $docs ?: null,
                'primary' => true,
            ], fn ($v) => $v !== null)],
            'meta' => ['resourceType' => 'ServiceProviderConfig', 'location' => $base . '/ServiceProviderConfig'],
        ], fn ($v) => $v !== null);
    }

    /** @return list<array<string,mixed>> */
    public static function resourceTypes(string $base): array
    {
        return [
            [
                'schemas' => [self::RESOURCE_TYPE],
                'id' => 'User',
                'name' => 'User',
                'endpoint' => '/Users',
                'description' => 'A person with an account in this academy',
                'schema' => self::USER,
                'schemaExtensions' => [['schema' => self::ENTERPRISE, 'required' => false]],
                'meta' => ['resourceType' => 'ResourceType', 'location' => $base . '/ResourceTypes/User'],
            ],
            [
                'schemas' => [self::RESOURCE_TYPE],
                'id' => 'Group',
                'name' => 'Group',
                'endpoint' => '/Groups',
                'description' => 'A group of users; membership gives access to what the group is enrolled in',
                'schema' => self::GROUP,
                'meta' => ['resourceType' => 'ResourceType', 'location' => $base . '/ResourceTypes/Group'],
            ],
        ];
    }

    /** @return list<array<string,mixed>> */
    public static function schemas(string $base): array
    {
        $attr = static fn (string $name, string $type, string $description, array $extra = []): array => $extra + [
            'name' => $name, 'type' => $type, 'multiValued' => false, 'description' => $description, 'required' => false,
            'caseExact' => false, 'mutability' => 'readWrite', 'returned' => 'default', 'uniqueness' => 'none',
        ];
        $meta = static fn (string $name) => ['resourceType' => 'Schema', 'location' => $base . '/Schemas/' . $name];

        return [
            [
                'schemas' => [self::SCHEMA],
                'id' => self::USER,
                'name' => 'User',
                'description' => 'User account',
                'attributes' => [
                    $attr('userName', 'string', 'Unique identifier of the user for this academy, usually the e-mail address', ['required' => true, 'uniqueness' => 'server']),
                    $attr('externalId', 'string', 'The identifier the provisioning client uses for the user', ['caseExact' => true]),
                    $attr('name', 'complex', 'The components of the user\'s name', ['subAttributes' => [
                        $attr('formatted', 'string', 'Full name, read from givenName and familyName', ['mutability' => 'readOnly']),
                        $attr('familyName', 'string', 'Family name'),
                        $attr('givenName', 'string', 'Given name'),
                    ]]),
                    $attr('displayName', 'string', 'Name shown for the user'),
                    $attr('title', 'string', 'Job title'),
                    $attr('emails', 'complex', 'E-mail address of the user (the address of the account; one is kept)', ['multiValued' => true, 'subAttributes' => [
                        $attr('value', 'string', 'E-mail address'),
                        $attr('type', 'string', 'Always work', ['canonicalValues' => ['work']]),
                        $attr('primary', 'boolean', 'Always true'),
                    ]]),
                    $attr('active', 'boolean', 'False blocks sign-in and revokes every session and token; true restores access'),
                    $attr('groups', 'complex', 'Groups of this provider the user belongs to', ['multiValued' => true, 'mutability' => 'readOnly', 'subAttributes' => [
                        $attr('value', 'string', 'Id of the group', ['mutability' => 'readOnly']),
                        $attr('$ref', 'reference', 'URI of the group', ['mutability' => 'readOnly', 'referenceTypes' => ['Group']]),
                        $attr('display', 'string', 'Name of the group', ['mutability' => 'readOnly']),
                    ]]),
                ],
                'meta' => $meta('User'),
            ],
            [
                'schemas' => [self::SCHEMA],
                'id' => self::ENTERPRISE,
                'name' => 'EnterpriseUser',
                'description' => 'Enterprise User',
                'attributes' => [
                    $attr('employeeNumber', 'string', 'Employee number'),
                    $attr('costCenter', 'string', 'Cost center'),
                    $attr('organization', 'string', 'Organization'),
                    $attr('division', 'string', 'Division'),
                    $attr('department', 'string', 'Department'),
                    $attr('manager', 'complex', 'The user\'s manager', ['subAttributes' => [
                        $attr('value', 'string', 'Id of the manager (a user of this provider)'),
                        $attr('$ref', 'reference', 'URI of the manager', ['mutability' => 'readOnly', 'referenceTypes' => ['User']]),
                        $attr('displayName', 'string', 'Name of the manager', ['mutability' => 'readOnly']),
                    ]]),
                ],
                'meta' => $meta('EnterpriseUser'),
            ],
            [
                'schemas' => [self::SCHEMA],
                'id' => self::GROUP,
                'name' => 'Group',
                'description' => 'Group',
                'attributes' => [
                    $attr('displayName', 'string', 'Name of the group', ['required' => true, 'uniqueness' => 'server']),
                    $attr('externalId', 'string', 'The identifier the provisioning client uses for the group', ['caseExact' => true]),
                    $attr('members', 'complex', 'Members of the group (users of this provider; nested groups are not supported)', ['multiValued' => true, 'subAttributes' => [
                        $attr('value', 'string', 'Id of the user', ['mutability' => 'immutable']),
                        $attr('$ref', 'reference', 'URI of the user', ['mutability' => 'immutable', 'referenceTypes' => ['User']]),
                        $attr('type', 'string', 'Always User', ['mutability' => 'immutable', 'canonicalValues' => ['User']]),
                        $attr('display', 'string', 'Name of the user', ['mutability' => 'readOnly']),
                    ]]),
                ],
                'meta' => $meta('Group'),
            ],
        ];
    }
}
