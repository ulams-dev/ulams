<?php

namespace Ulams\Auth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Passport;
use Ulams\Auth\Http\Controllers\Swagger\TokenSwagger;
use Ulams\Auth\Http\Requests\Tokens\CreateTokenRequest;
use Ulams\Auth\Http\Requests\Tokens\CurrentTokenRequest;
use Ulams\Auth\Http\Requests\Tokens\ListTokensRequest;
use Ulams\Auth\Http\Requests\Tokens\RevokeTokenRequest;
use Ulams\Auth\Http\Resources\ApiTokenCreatedResource;
use Ulams\Auth\Http\Resources\ApiTokenResource;
use Ulams\Auth\Services\Contracts\PersonalAccessTokenServiceContract;
use Ulams\Auth\Support\TokenContext;
use Ulams\Auth\Support\TokenScopes;
use Ulams\Core\Http\Controllers\UlamsBaseController;

/** The signed-in user's own scoped personal access tokens (ADR 0074). */
class TokenController extends UlamsBaseController implements TokenSwagger
{
    public function __construct(private PersonalAccessTokenServiceContract $tokens)
    {
    }

    public function index(ListTokensRequest $request): JsonResponse
    {
        $list = $this->tokens->listFor($request->user(), $request->boolean('include_revoked'));

        return $this->sendResponseForResource(ApiTokenResource::collection($list), __('Token list'));
    }

    public function store(CreateTokenRequest $request): JsonResponse
    {
        $data = $request->validated();
        try {
            $issued = $this->tokens->issue(
                $request->user(),
                $data['name'],
                TokenScopes::normalize($data['scopes']),
                (int) ($data['expires_in_days'] ?? 90),
                $data['kind'] ?? 'cli',
                $data['agent_name'] ?? null,
                $request->header('X-Ulams-Client') === 'cli' ? 'cli' : 'admin',
                isset($data['rate_limit_per_minute']) ? (int) $data['rate_limit_per_minute'] : null,
                TokenContext::resolve($request)?->scopes,
            );
        } catch (InvalidArgumentException $e) {
            return $this->sendError($e->getMessage(), 422);
        }

        return $this->sendResponse(ApiTokenCreatedResource::make($issued)->toArray($request), __('Token created. Copy it now: it is shown only once.'), 201);
    }

    public function destroy(RevokeTokenRequest $request): JsonResponse
    {
        $this->tokens->revoke($request->tokenMeta());

        return $this->sendSuccess(__('Token revoked'));
    }

    public function current(CurrentTokenRequest $request): JsonResponse
    {
        $user = $request->user();
        $context = TokenContext::resolve($request);
        $access = $user->token();
        $tokenId = $context?->tokenId ?? ($access instanceof AccessToken ? $access->oauth_access_token_id : null);
        $token = $tokenId ? Passport::token()->newQuery()->find($tokenId) : null;

        return $this->sendResponse([
            'id' => $tokenId,
            'name' => $token?->name,
            'scoped' => $context !== null,
            'scopes' => $context?->scopes ?? ['*'],
            'kind' => $context?->meta->kind,
            'agent_name' => $context?->meta->agent_name,
            'created_via' => $context?->meta->created_via,
            'expires_at' => $token?->expires_at?->toIso8601String(),
            'last_used_at' => $context?->meta->last_used_at?->toIso8601String(),
            'user' => ['id' => $user->getKey(), 'name' => $user->name, 'email' => $user->email],
            'host' => $request->getHost(),
            'host_kind' => TokenScopes::isPlatformHost($request->getHost()) ? 'platform' : 'tenant',
        ], __('Current token'));
    }
}
