<?php

namespace Ulams\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;
use Ulams\Auth\Models\ApiTokenMeta;
use Ulams\Auth\Support\TokenContext;
use Ulams\Identity\Models\IdentityProvider;
use Ulams\Identity\Services\Scim\ScimError;

/**
 * Authentication of the SCIM routes, answering in the SCIM error format. Only a token of kind `scim` is accepted, and
 * it is bound to exactly one identity provider (the one whose service user holds it); an unscoped login token, a
 * personal access token of any other kind, a token of another tenant (signed with another tenant's key, so not even
 * a token) or a token whose provider was deleted is refused. The rate limit is per token.
 */
class ScimAuthenticate
{
    public const PROVIDER = 'ulams.scim.provider';

    public function handle(Request $request, Closure $next): Response
    {
        if (!config('ulams_identity.enabled')) {
            return $this->respond(new ScimError(404, null, 'SCIM is switched off on this deployment.'));
        }
        $user = $request->bearerToken() ? Auth::guard('api')->user() : null;
        if ($user === null) {
            return $this->respond(new ScimError(401, null, 'A valid SCIM bearer token is required.', ['WWW-Authenticate' => 'Bearer realm="scim"']));
        }
        $context = TokenContext::resolve($request);
        if ($context === null || $context->meta->kind !== ApiTokenMeta::KIND_SCIM) {
            return $this->respond(ScimError::forbidden('This token cannot be used for SCIM. Create a SCIM token for the identity provider.'));
        }
        $provider = IdentityProvider::query()->where('scim_user_id', $user->getAuthIdentifier())->first();
        if ($provider === null) {
            return $this->respond(ScimError::forbidden('The identity provider of this token no longer exists.'));
        }

        $key = 'scim:' . $context->tokenId;
        $limit = (int) config('ulams_identity.scim.rate_limit');
        if (RateLimiter::tooManyAttempts($key, $limit)) {
            $request->attributes->set('ulams.rate_limited', true);

            return $this->respond(new ScimError(429, null, 'Too many requests: the limit is ' . $limit . ' per minute and token.', ['Retry-After' => (string) RateLimiter::availableIn($key)]));
        }
        RateLimiter::hit($key, 60);

        $request->attributes->set(self::PROVIDER, $provider);

        return $next($request);
    }

    private function respond(ScimError $error): Response
    {
        return response()->json($error->toArray(), $error->status, $error->headers + ['Content-Type' => 'application/scim+json; charset=utf-8']);
    }
}
