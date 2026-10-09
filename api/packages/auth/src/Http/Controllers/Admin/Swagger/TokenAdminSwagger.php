<?php

namespace Ulams\Auth\Http\Controllers\Admin\Swagger;

use Illuminate\Http\JsonResponse;
use Ulams\Auth\Http\Requests\Tokens\AdminListTokensRequest;
use Ulams\Auth\Http\Requests\Tokens\AgentAuditListRequest;
use Ulams\Auth\Http\Requests\Tokens\RevokeTokenRequest;
use Ulams\Auth\Http\Requests\Tokens\TokenAuditRequest;

interface TokenAdminSwagger
{
    /**
     * @OA\Get(path="/api/admin/tokens", summary="Every user's scoped API tokens", tags={"Admin Tokens"}, security={{"passport": {}}},
     *     @OA\Parameter(name="user_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="kind", in="query", @OA\Schema(type="string", enum={"cli","agent","ci","integration"})),
     *     @OA\Parameter(name="include_revoked", in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Paginated tokens", @OA\JsonContent(ref="#/components/schemas/ApiTokenList")),
     *     @OA\Response(response=401, description="Unauthenticated"), @OA\Response(response=403, description="Needs token_manage"))
     */
    public function index(AdminListTokensRequest $request): JsonResponse;

    /**
     * @OA\Delete(path="/api/admin/tokens/{id}", summary="Revoke any scoped token", tags={"Admin Tokens"}, security={{"passport": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Revoked", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"))),
     *     @OA\Response(response=401, description="Unauthenticated"), @OA\Response(response=403, description="Needs token_manage"), @OA\Response(response=404, description="Unknown token"))
     */
    public function destroy(RevokeTokenRequest $request): JsonResponse;

    /**
     * @OA\Get(path="/api/admin/tokens/{id}/audit", summary="Audit log of one token", tags={"Admin Tokens"}, security={{"passport": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Paginated audit rows", @OA\JsonContent(ref="#/components/schemas/AgentAuditList")),
     *     @OA\Response(response=401, description="Unauthenticated"), @OA\Response(response=403, description="Needs token_manage or token owner"), @OA\Response(response=404, description="Unknown token"))
     */
    public function audit(TokenAuditRequest $request): JsonResponse;

    /**
     * @OA\Get(path="/api/admin/agent-audit", summary="Agent audit log", tags={"Admin Tokens"}, security={{"passport": {}}},
     *     @OA\Parameter(name="token_id", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="user_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="from", in="query", @OA\Schema(type="string", format="date-time")),
     *     @OA\Parameter(name="to", in="query", @OA\Schema(type="string", format="date-time")),
     *     @OA\Response(response=200, description="Paginated audit rows", @OA\JsonContent(ref="#/components/schemas/AgentAuditList")),
     *     @OA\Response(response=401, description="Unauthenticated"), @OA\Response(response=403, description="Needs token_manage"))
     */
    public function agentAudit(AgentAuditListRequest $request): JsonResponse;
}
