<?php

namespace Ulams\Auth\Support;

use Illuminate\Http\Request;
use Laravel\Passport\AccessToken;
use Ulams\Auth\Models\ApiTokenMeta;

/**
 * The scoped token behind a request, if any. A request without a bearer token, with a login, LTI
 * or demo token (no `api_token_meta` row) or with a transient cookie token has no context and is
 * handled exactly as before scoped tokens existed.
 */
final class TokenContext
{
    private const ATTRIBUTE = 'ulams.token_context';

    /** @param list<string> $scopes */
    public function __construct(
        public readonly string $tokenId,
        public readonly array $scopes,
        public readonly ApiTokenMeta $meta,
        public readonly mixed $user,
    ) {
    }

    public static function resolve(Request $request): ?self
    {
        if ($request->attributes->has(self::ATTRIBUTE)) {
            return $request->attributes->get(self::ATTRIBUTE) ?: null;
        }
        $context = self::build($request);
        $request->attributes->set(self::ATTRIBUTE, $context ?? false);

        return $context;
    }

    private static function build(Request $request): ?self
    {
        if (!$request->bearerToken()) {
            return null;
        }
        $user = $request->user('api');
        $token = $user !== null && method_exists($user, 'token') ? $user->token() : null;
        $tokenId = $token instanceof AccessToken ? ($token->oauth_access_token_id ?? null) : null;
        if (!is_string($tokenId) || $tokenId === '') {
            return null;
        }
        $meta = ApiTokenMeta::query()->where('token_id', $tokenId)->first();
        if ($meta === null) {
            return null;
        }

        return new self($tokenId, array_values((array) ($token->oauth_scopes ?? [])), $meta, $user);
    }
}
