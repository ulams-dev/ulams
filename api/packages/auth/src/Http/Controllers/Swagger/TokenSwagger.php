<?php

namespace Ulams\Auth\Http\Controllers\Swagger;

use Illuminate\Http\JsonResponse;
use Ulams\Auth\Http\Requests\Tokens\CreateTokenRequest;
use Ulams\Auth\Http\Requests\Tokens\CurrentTokenRequest;
use Ulams\Auth\Http\Requests\Tokens\ListTokensRequest;
use Ulams\Auth\Http\Requests\Tokens\RevokeTokenRequest;
use Ulams\Auth\Http\Requests\Tokens\TokenUsageRequest;
use Ulams\Auth\Http\Requests\Tokens\UpdateTokenRequest;

interface TokenSwagger
{
    /**
     * @OA\Get(path="/api/auth/tokens", operationId="auth.tokens.get", summary="My scoped API tokens", tags={"Auth"}, security={{"passport": {}}},
     *     @OA\Parameter(name="include_revoked", in="query", @OA\Schema(type="boolean")),
     *     @OA\Response(response=200, description="Active scoped tokens of the signed-in user", @OA\JsonContent(
     *         @OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
     *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/ApiToken")))),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Token lacks the tokens:read scope", @OA\JsonContent(ref="#/components/schemas/ScopeError")))
     */
    public function index(ListTokensRequest $request): JsonResponse;

    /**
     * @OA\Post(path="/api/auth/tokens", operationId="auth.tokens.post", summary="Create a scoped API token (the secret is returned once)", tags={"Auth"}, security={{"passport": {}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"name","scopes"},
     *         @OA\Property(property="name", type="string", maxLength=100, example="ulams-cli on my laptop"),
     *         @OA\Property(property="scopes", type="array", description="Scopes or @presets (read-only, author, admin, learner, ci)", @OA\Items(type="string", example="courses:write")),
     *         @OA\Property(property="expires_in_days", type="integer", minimum=1, maximum=365, example=90),
     *         @OA\Property(property="kind", type="string", enum={"cli","agent","ci","integration"}),
     *         @OA\Property(property="agent_name", type="string", nullable=true),
     *         @OA\Property(property="rate_limit_per_minute", type="integer", nullable=true))),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(
     *         @OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
     *         @OA\Property(property="data", ref="#/components/schemas/ApiTokenCreated"))),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Token lacks tokens:write", @OA\JsonContent(ref="#/components/schemas/ScopeError")),
     *     @OA\Response(response=422, description="Invalid scopes, lifetime, limit reached or scopes exceed the calling token's"))
     */
    public function store(CreateTokenRequest $request): JsonResponse;

    /**
     * @OA\Delete(path="/api/auth/tokens/{id}", operationId="auth.tokens.by-id.delete", summary="Revoke one of my scoped tokens", tags={"Auth"}, security={{"passport": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Revoked", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"))),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Somebody else's token"),
     *     @OA\Response(response=404, description="Unknown token"))
     */
    public function destroy(RevokeTokenRequest $request): JsonResponse;

    /**
     * @OA\Patch(path="/api/auth/tokens/{id}", operationId="auth.tokens.by-id.patch", summary="Change the per-minute rate limit of one of my tokens", tags={"Auth"}, security={{"passport": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"rate_limit_per_minute"},
     *         @OA\Property(property="rate_limit_per_minute", type="integer", nullable=true, minimum=1, maximum=6000, description="null removes the limit"))),
     *     @OA\Response(response=200, description="Updated token", @OA\JsonContent(
     *         @OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
     *         @OA\Property(property="data", ref="#/components/schemas/ApiToken"))),
     *     @OA\Response(response=401, description="Unauthenticated"), @OA\Response(response=403, description="Somebody else's token"),
     *     @OA\Response(response=404, description="Unknown token"), @OA\Response(response=422, description="Invalid limit"))
     */
    public function update(UpdateTokenRequest $request): JsonResponse;

    /**
     * @OA\Get(path="/api/auth/tokens/{id}/usage", operationId="auth.tokens.by-id.usage.get", summary="Request counts of one of my tokens per day, and its rate limit window", tags={"Auth"}, security={{"passport": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="days", in="query", @OA\Schema(type="integer", minimum=1, maximum=90, default=30)),
     *     @OA\Response(response=200, description="Usage", @OA\JsonContent(
     *         @OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
     *         @OA\Property(property="data", ref="#/components/schemas/ApiTokenUsage"))),
     *     @OA\Response(response=401, description="Unauthenticated"), @OA\Response(response=403, description="Somebody else's token"), @OA\Response(response=404, description="Unknown token"))
     */
    public function usage(TokenUsageRequest $request): JsonResponse;

    /**
     * @OA\Get(path="/api/auth/tokens/current", operationId="auth.tokens.current.get", summary="The token this request is authenticated with", tags={"Auth"}, security={{"passport": {}}},
     *     @OA\Response(response=200, description="Token, user and host", @OA\JsonContent(
     *         @OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
     *         @OA\Property(property="data", type="object",
     *             @OA\Property(property="id", type="string", nullable=true), @OA\Property(property="name", type="string", nullable=true),
     *             @OA\Property(property="scoped", type="boolean"),
     *             @OA\Property(property="scopes", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="kind", type="string", nullable=true), @OA\Property(property="agent_name", type="string", nullable=true),
     *             @OA\Property(property="created_via", type="string", nullable=true),
     *             @OA\Property(property="expires_at", type="string", format="date-time", nullable=true),
     *             @OA\Property(property="last_used_at", type="string", format="date-time", nullable=true),
     *             @OA\Property(property="user", type="object", @OA\Property(property="id", type="integer"), @OA\Property(property="name", type="string"), @OA\Property(property="email", type="string")),
     *             @OA\Property(property="host", type="string"), @OA\Property(property="host_kind", type="string", enum={"tenant","platform"})))),
     *     @OA\Response(response=401, description="Unauthenticated"))
     */
    public function current(CurrentTokenRequest $request): JsonResponse;
}
