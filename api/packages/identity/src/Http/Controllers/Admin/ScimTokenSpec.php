<?php

namespace Ulams\Identity\Http\Controllers\Admin;

/**
 * OpenAPI description of the SCIM token endpoints of an identity provider (permission identity_scim_manage).
 *
 * @OA\Get(path="/api/admin/identity/providers/{provider}/scim-tokens", operationId="identity.providers.scim-tokens.list", summary="SCIM tokens of an identity provider, active and revoked", tags={"Admin Identity"}, security={{"passport": {}}},
 *     @OA\Parameter(name="provider", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\Response(response=200, description="Tokens (the secret is never listed)", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
 *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/ApiToken")))),
 *     @OA\Response(response=403, description="Needs identity_scim_manage"),
 *     @OA\Response(response=404, description="Not found"))
 * @OA\Post(path="/api/admin/identity/providers/{provider}/scim-tokens", operationId="identity.providers.scim-tokens.create", summary="Create a SCIM token for an identity provider (shown once; at most two active, for rotation)", tags={"Admin Identity"}, security={{"passport": {}}},
 *     @OA\Parameter(name="provider", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(required={"name"}, @OA\Property(property="name", type="string", maxLength=100), @OA\Property(property="expires_in_days", type="integer", minimum=1, maximum=365, description="Default 365")),),
 *     @OA\Response(response=201, description="Created: copy the token now", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
 *         @OA\Property(property="data", ref="#/components/schemas/ApiTokenCreated"))),
 *     @OA\Response(response=403, description="Needs identity_scim_manage"),
 *     @OA\Response(response=404, description="Not found"),
 *     @OA\Response(response=422, description="Invalid input, or two tokens are already active"))
 * @OA\Delete(path="/api/admin/identity/providers/{provider}/scim-tokens/{token}", operationId="identity.providers.scim-tokens.revoke", summary="Revoke a SCIM token", tags={"Admin Identity"}, security={{"passport": {}}},
 *     @OA\Parameter(name="provider", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\Parameter(name="token", in="path", required=true, @OA\Schema(type="string"), description="Id of the token"),
 *     @OA\Response(response=200, description="Revoked", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"))),
 *     @OA\Response(response=403, description="Needs identity_scim_manage"),
 *     @OA\Response(response=404, description="Not found"))
 */
final class ScimTokenSpec
{
}
