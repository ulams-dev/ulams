<?php

namespace Ulams\Identity\Http\Controllers\Scim;

/**
 * OpenAPI description of the SCIM 2.0 server (RFC 7643, 7644) at /api/scim/v2. It is for identity providers (Entra ID,
 * Okta, Keycloak and others) and is excluded from the CLI (front/cli/spec/exclusions.yaml). Every request needs a
 * SCIM bearer token (`Authorization: Bearer ulams_pat_...`) created for one identity provider; errors use the SCIM
 * error schema with `scimType`; responses are `application/scim+json` with an `ETag` header.
 *
 * @OA\SecurityScheme(securityScheme="scim", type="http", scheme="bearer", description="A SCIM token of one identity provider (POST /api/admin/identity/providers/{provider}/scim-tokens)")
 *
 * @OA\Schema(schema="ScimError", required={"schemas","status"},
 *     @OA\Property(property="schemas", type="array", @OA\Items(type="string", example="urn:ietf:params:scim:api:messages:2.0:Error")),
 *     @OA\Property(property="status", type="string", example="409"),
 *     @OA\Property(property="scimType", type="string", enum={"invalidFilter","tooMany","uniqueness","mutability","invalidSyntax","invalidPath","noTarget","invalidValue","invalidVers","sensitive"}),
 *     @OA\Property(property="detail", type="string"))
 * @OA\Schema(schema="ScimResource", type="object", description="A SCIM discovery document (ServiceProviderConfig, ResourceType or Schema)", additionalProperties=true)
 * @OA\Schema(schema="ScimListResponse", required={"schemas","totalResults","Resources"},
 *     @OA\Property(property="schemas", type="array", @OA\Items(type="string", example="urn:ietf:params:scim:api:messages:2.0:ListResponse")),
 *     @OA\Property(property="totalResults", type="integer"), @OA\Property(property="startIndex", type="integer"), @OA\Property(property="itemsPerPage", type="integer"),
 *     @OA\Property(property="Resources", type="array", @OA\Items(type="object", additionalProperties=true)))
 * @OA\Schema(schema="ScimUser", required={"schemas","userName"},
 *     @OA\Property(property="schemas", type="array", @OA\Items(type="string", example="urn:ietf:params:scim:schemas:core:2.0:User")),
 *     @OA\Property(property="id", type="string", readOnly=true, description="The id of the user in this academy"),
 *     @OA\Property(property="externalId", type="string", description="The client's own id, unique per provider"),
 *     @OA\Property(property="userName", type="string", description="Unique in the academy, case-insensitive; usually the e-mail address"),
 *     @OA\Property(property="name", type="object", @OA\Property(property="givenName", type="string"), @OA\Property(property="familyName", type="string"), @OA\Property(property="formatted", type="string", readOnly=true)),
 *     @OA\Property(property="displayName", type="string"), @OA\Property(property="title", type="string"),
 *     @OA\Property(property="emails", type="array", description="The address of the account; one is kept (primary, else the first, else the userName)", @OA\Items(type="object", @OA\Property(property="value", type="string"), @OA\Property(property="type", type="string", example="work"), @OA\Property(property="primary", type="boolean"))),
 *     @OA\Property(property="active", type="boolean", description="false blocks sign-in and revokes every session and token; true restores access"),
 *     @OA\Property(property="groups", type="array", readOnly=true, @OA\Items(type="object", @OA\Property(property="value", type="string"), @OA\Property(property="$ref", type="string"), @OA\Property(property="display", type="string"))),
 *     @OA\Property(property="urn:ietf:params:scim:schemas:extension:enterprise:2.0:User", type="object",
 *         @OA\Property(property="employeeNumber", type="string"), @OA\Property(property="costCenter", type="string"), @OA\Property(property="organization", type="string"),
 *         @OA\Property(property="division", type="string"), @OA\Property(property="department", type="string"),
 *         @OA\Property(property="manager", type="object", @OA\Property(property="value", type="string", description="Id of a user of this provider"), @OA\Property(property="$ref", type="string", readOnly=true), @OA\Property(property="displayName", type="string", readOnly=true))),
 *     @OA\Property(property="meta", type="object", readOnly=true, @OA\Property(property="resourceType", type="string"), @OA\Property(property="created", type="string", format="date-time"),
 *         @OA\Property(property="lastModified", type="string", format="date-time"), @OA\Property(property="location", type="string", format="uri"), @OA\Property(property="version", type="string", description="Weak ETag of the representation")))
 * @OA\Schema(schema="ScimUserList", allOf={@OA\Schema(ref="#/components/schemas/ScimListResponse")},
 *     @OA\Property(property="Resources", type="array", @OA\Items(ref="#/components/schemas/ScimUser")))
 * @OA\Schema(schema="ScimGroup", required={"schemas","displayName"},
 *     @OA\Property(property="schemas", type="array", @OA\Items(type="string", example="urn:ietf:params:scim:schemas:core:2.0:Group")),
 *     @OA\Property(property="id", type="string", readOnly=true),
 *     @OA\Property(property="externalId", type="string", description="The client's own id, unique per provider"),
 *     @OA\Property(property="displayName", type="string", description="Name of the group; case-insensitively unique among all groups of the academy"),
 *     @OA\Property(property="members", type="array", description="Users of this provider (nested groups are not supported). Only members this provider provisioned are shown and changed", @OA\Items(type="object",
 *         @OA\Property(property="value", type="string", description="Id of the user"), @OA\Property(property="$ref", type="string", readOnly=true), @OA\Property(property="type", type="string", example="User"), @OA\Property(property="display", type="string", readOnly=true))),
 *     @OA\Property(property="meta", type="object", readOnly=true, @OA\Property(property="resourceType", type="string"), @OA\Property(property="created", type="string", format="date-time"),
 *         @OA\Property(property="lastModified", type="string", format="date-time"), @OA\Property(property="location", type="string", format="uri"), @OA\Property(property="version", type="string", description="Weak ETag of the representation")))
 * @OA\Schema(schema="ScimGroupList", allOf={@OA\Schema(ref="#/components/schemas/ScimListResponse")},
 *     @OA\Property(property="Resources", type="array", @OA\Items(ref="#/components/schemas/ScimGroup")))
 *
 * @OA\Schema(schema="ScimPatchOp", required={"schemas","Operations"},
 *     @OA\Property(property="schemas", type="array", @OA\Items(type="string", example="urn:ietf:params:scim:api:messages:2.0:PatchOp")),
 *     @OA\Property(property="Operations", type="array", @OA\Items(type="object", required={"op"},
 *         @OA\Property(property="op", type="string", description="add, replace or remove, in any letter case"),
 *         @OA\Property(property="path", type="string", description="Attribute path, with sub-attribute, value path or schema URN prefix; required for remove"),
 *         @OA\Property(property="value"))))
 * @OA\Schema(schema="ScimSearchRequest", required={"schemas"},
 *     @OA\Property(property="schemas", type="array", @OA\Items(type="string", example="urn:ietf:params:scim:api:messages:2.0:SearchRequest")),
 *     @OA\Property(property="filter", type="string"), @OA\Property(property="startIndex", type="integer"), @OA\Property(property="count", type="integer"),
 *     @OA\Property(property="attributes", type="array", @OA\Items(type="string")), @OA\Property(property="excludedAttributes", type="array", @OA\Items(type="string")))
 *
 * @OA\Get(path="/api/scim/v2/ServiceProviderConfig", operationId="scim.service-provider-config.get", summary="SCIM service provider configuration (RFC 7644 4)", tags={"SCIM"}, security={{"scim": {}}},
 *     @OA\Response(response=200, description="Capabilities of this SCIM server", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimResource"))), @OA\Response(response=401, description="No valid SCIM token", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimError"))))
 *
 * @OA\Get(path="/api/scim/v2/ResourceTypes", operationId="scim.resource-types.list", summary="SCIM resource types", tags={"SCIM"}, security={{"scim": {}}},
 *     @OA\Response(response=200, description="User and Group", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimListResponse"))), @OA\Response(response=401, description="No valid SCIM token", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimError"))))
 *
 * @OA\Get(path="/api/scim/v2/ResourceTypes/{id}", operationId="scim.resource-types.get", summary="One SCIM resource type", tags={"SCIM"}, security={{"scim": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="The resource type", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimResource"))), @OA\Response(response=404, description="Unknown resource type", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimError"))))
 *
 * @OA\Get(path="/api/scim/v2/Schemas", operationId="scim.schemas.list", summary="SCIM schemas (User, EnterpriseUser, Group)", tags={"SCIM"}, security={{"scim": {}}},
 *     @OA\Response(response=200, description="Schema definitions", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimListResponse"))), @OA\Response(response=401, description="No valid SCIM token", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimError"))))
 *
 * @OA\Get(path="/api/scim/v2/Schemas/{id}", operationId="scim.schemas.get", summary="One SCIM schema by its URN", tags={"SCIM"}, security={{"scim": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="The schema", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimResource"))), @OA\Response(response=404, description="Unknown schema", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimError"))))
 *
 * @OA\Get(path="/api/scim/v2/Users", operationId="scim.users.list", summary="List or filter the users this identity provider provisioned", tags={"SCIM"}, security={{"scim": {}}},
 *     @OA\Parameter(name="filter", in="query", @OA\Schema(type="string"), description="RFC 7644 filter: eq ne co sw ew gt ge lt le pr, and, or, not, value paths. Attributes: id, userName, externalId, emails.value, emails[...], active, name.givenName, name.familyName, meta.lastModified, meta.created"), @OA\Parameter(name="startIndex", in="query", @OA\Schema(type="integer", minimum=1), description="1-based, default 1"), @OA\Parameter(name="count", in="query", @OA\Schema(type="integer", minimum=0, maximum=200), description="Default 100, at most 200; 0 returns only totalResults"), @OA\Parameter(name="attributes", in="query", @OA\Schema(type="string"), description="Comma-separated attributes to return"), @OA\Parameter(name="excludedAttributes", in="query", @OA\Schema(type="string"), description="Comma-separated attributes to leave out"),
 *     @OA\Response(response=200, description="A page of users", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimUserList"))), @OA\Response(response=400, description="invalidFilter or invalidValue", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimError"))), @OA\Response(response=401, description="No valid SCIM token", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimError"))))
 *
 * @OA\Post(path="/api/scim/v2/Users", operationId="scim.users.create", summary="Create a user (or adopt an existing account the provider is trusted for)", tags={"SCIM"}, security={{"scim": {}}},
 *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimUser"))),
 *     @OA\Response(response=201, description="Created: Location and ETag headers set", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimUser"))), @OA\Response(response=400, description="invalidSyntax or invalidValue", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimError"))), @OA\Response(response=409, description="uniqueness: userName, externalId or e-mail already used", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimError"))))
 *
 * @OA\Post(path="/api/scim/v2/Users/.search", operationId="scim.users.search", summary="Search users with a request body (RFC 7644 3.4.3)", tags={"SCIM"}, security={{"scim": {}}},
 *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimSearchRequest"))),
 *     @OA\Response(response=200, description="A page of users", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimUserList"))), @OA\Response(response=400, description="invalidSyntax or invalidFilter", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimError"))))
 *
 * @OA\Get(path="/api/scim/v2/Users/{id}", operationId="scim.users.get", summary="One user", tags={"SCIM"}, security={{"scim": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")), @OA\Parameter(name="If-None-Match", in="header", @OA\Schema(type="string"), description="The version you have; a match answers 304"), @OA\Parameter(name="attributes", in="query", @OA\Schema(type="string")), @OA\Parameter(name="excludedAttributes", in="query", @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="The user with its ETag", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimUser"))), @OA\Response(response=304, description="Not modified"), @OA\Response(response=404, description="Not a user of this provider", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimError"))))
 *
 * @OA\Put(path="/api/scim/v2/Users/{id}", operationId="scim.users.replace", summary="Replace a user (omitted attributes are cleared; an omitted active keeps the current state)", tags={"SCIM"}, security={{"scim": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")), @OA\Parameter(name="If-Match", in="header", required=false, description="A version (ETag) from meta.version; a stale one answers 412", @OA\Schema(type="string")),
 *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimUser"))),
 *     @OA\Response(response=200, description="The updated user", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimUser"))), @OA\Response(response=400, description="invalidValue or mutability", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimError"))), @OA\Response(response=403, description="Administrators cannot be changed", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimError"))), @OA\Response(response=404, description="Not a user of this provider", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimError"))), @OA\Response(response=409, description="uniqueness", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimError"))), @OA\Response(response=412, description="Stale If-Match", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimError"))))
 *
 * @OA\Patch(path="/api/scim/v2/Users/{id}", operationId="scim.users.patch", summary="Change a user with add, replace and remove operations (all or none)", tags={"SCIM"}, security={{"scim": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")), @OA\Parameter(name="If-Match", in="header", required=false, description="A version (ETag) from meta.version; a stale one answers 412", @OA\Schema(type="string")),
 *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimPatchOp"))),
 *     @OA\Response(response=200, description="The updated user", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimUser"))), @OA\Response(response=400, description="invalidSyntax, invalidPath, invalidValue, noTarget or mutability", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimError"))), @OA\Response(response=403, description="Administrators cannot be changed", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimError"))), @OA\Response(response=404, description="Not a user of this provider", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimError"))), @OA\Response(response=409, description="uniqueness", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimError"))), @OA\Response(response=412, description="Stale If-Match", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimError"))))
 *
 * @OA\Delete(path="/api/scim/v2/Users/{id}", operationId="scim.users.delete", summary="Deprovision a user: deactivate, revoke every session and token, keep the learning record (anonymise when configured)", tags={"SCIM"}, security={{"scim": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")), @OA\Parameter(name="If-Match", in="header", required=false, description="A version (ETag) from meta.version; a stale one answers 412", @OA\Schema(type="string")),
 *     @OA\Response(response=204, description="Deprovisioned"), @OA\Response(response=403, description="Administrators cannot be changed", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimError"))), @OA\Response(response=404, description="Not a user of this provider", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimError"))), @OA\Response(response=412, description="Stale If-Match", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimError"))))
 *
 * @OA\Get(path="/api/scim/v2/Groups", operationId="scim.groups.list", summary="List or filter the groups this identity provider created", tags={"SCIM"}, security={{"scim": {}}},
 *     @OA\Parameter(name="filter", in="query", @OA\Schema(type="string"), description="RFC 7644 filter. Attributes: id, displayName, externalId, members.value, members[value eq ...], meta.lastModified, meta.created"), @OA\Parameter(name="startIndex", in="query", @OA\Schema(type="integer", minimum=1), description="1-based, default 1"), @OA\Parameter(name="count", in="query", @OA\Schema(type="integer", minimum=0, maximum=200), description="Default 100, at most 200; 0 returns only totalResults"), @OA\Parameter(name="attributes", in="query", @OA\Schema(type="string"), description="Comma-separated attributes to return"), @OA\Parameter(name="excludedAttributes", in="query", @OA\Schema(type="string"), description="Comma-separated attributes to leave out, for example members"),
 *     @OA\Response(response=200, description="A page of groups", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimGroupList"))), @OA\Response(response=400, description="invalidFilter or invalidValue", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimError"))))
 *
 * @OA\Post(path="/api/scim/v2/Groups", operationId="scim.groups.create", summary="Create a group, optionally with members (users of this provider)", tags={"SCIM"}, security={{"scim": {}}},
 *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimGroup"))),
 *     @OA\Response(response=201, description="Created: Location and ETag headers set", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimGroup"))), @OA\Response(response=400, description="invalidSyntax or invalidValue, for example a member that is not a user of this provider", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimError"))), @OA\Response(response=409, description="uniqueness: the displayName (also of groups this provider did not create) or externalId is used", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimError"))))
 *
 * @OA\Post(path="/api/scim/v2/Groups/.search", operationId="scim.groups.search", summary="Search groups with a request body (RFC 7644 3.4.3)", tags={"SCIM"}, security={{"scim": {}}},
 *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimSearchRequest"))),
 *     @OA\Response(response=200, description="A page of groups", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimGroupList"))), @OA\Response(response=400, description="invalidSyntax or invalidFilter", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimError"))))
 *
 * @OA\Get(path="/api/scim/v2/Groups/{id}", operationId="scim.groups.get", summary="One group with the members this provider provisioned", tags={"SCIM"}, security={{"scim": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")), @OA\Parameter(name="If-None-Match", in="header", @OA\Schema(type="string"), description="The version you have; a match answers 304"), @OA\Parameter(name="attributes", in="query", @OA\Schema(type="string")), @OA\Parameter(name="excludedAttributes", in="query", @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="The group with its ETag", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimGroup"))), @OA\Response(response=304, description="Not modified"), @OA\Response(response=404, description="Not a group of this provider", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimError"))))
 *
 * @OA\Put(path="/api/scim/v2/Groups/{id}", operationId="scim.groups.replace", summary="Replace a group (a missing members attribute keeps the membership; an empty list empties it)", tags={"SCIM"}, security={{"scim": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")), @OA\Parameter(name="If-Match", in="header", required=false, description="A version (ETag) from meta.version; a stale one answers 412", @OA\Schema(type="string")),
 *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimGroup"))),
 *     @OA\Response(response=200, description="The updated group", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimGroup"))), @OA\Response(response=400, description="invalidValue or mutability", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimError"))), @OA\Response(response=404, description="Not a group of this provider", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimError"))), @OA\Response(response=409, description="uniqueness", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimError"))), @OA\Response(response=412, description="Stale If-Match", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimError"))))
 *
 * @OA\Patch(path="/api/scim/v2/Groups/{id}", operationId="scim.groups.patch", summary="Rename a group and add, replace or remove members (all or none)", tags={"SCIM"}, security={{"scim": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")), @OA\Parameter(name="If-Match", in="header", required=false, description="A version (ETag) from meta.version; a stale one answers 412", @OA\Schema(type="string")),
 *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimPatchOp"))),
 *     @OA\Response(response=200, description="The updated group", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimGroup"))), @OA\Response(response=400, description="invalidSyntax, invalidPath, invalidValue, noTarget or mutability", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimError"))), @OA\Response(response=404, description="Not a group of this provider", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimError"))), @OA\Response(response=409, description="uniqueness", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimError"))), @OA\Response(response=412, description="Stale If-Match", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimError"))))
 *
 * @OA\Delete(path="/api/scim/v2/Groups/{id}", operationId="scim.groups.delete", summary="Delete a group: the access it gave goes, the people stay", tags={"SCIM"}, security={{"scim": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")), @OA\Parameter(name="If-Match", in="header", required=false, description="A version (ETag) from meta.version; a stale one answers 412", @OA\Schema(type="string")),
 *     @OA\Response(response=204, description="Deleted"), @OA\Response(response=404, description="Not a group of this provider", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimError"))), @OA\Response(response=412, description="Stale If-Match", @OA\MediaType(mediaType="application/scim+json", @OA\Schema(ref="#/components/schemas/ScimError"))))
 */
final class ScimSpec
{
}
