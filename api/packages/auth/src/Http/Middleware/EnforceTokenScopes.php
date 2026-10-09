<?php

namespace Ulams\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;
use Ulams\Auth\Support\TokenContext;
use Ulams\Auth\Support\TokenScopes;

/**
 * Enforces the scopes of a scoped personal access token (ADR 0074, docs/plans/cli.md 6.2). Only
 * tokens with an `api_token_meta` row are affected; login, LTI and demo tokens behave as before.
 *
 * GET/HEAD need `<area>:read`, everything else `<area>:write`; `*` passes. A route that is not in
 * `resources/token-scopes.php` is denied (fail closed). Scopes only narrow: the user's own
 * permissions are still checked by the route's policies.
 */
class EnforceTokenScopes
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = TokenContext::resolve($request);
        if ($context === null) {
            return $next($request);
        }

        $route = $request->route();
        $requirement = $route !== null ? TokenScopes::requirement($route->uri(), $request->getMethod()) : null;
        $area = $requirement['area'] ?? null;
        $request->attributes->set('ulams.scope_area', $area);

        if ($area === 'none') {
            return $this->deny('This endpoint cannot be used with an API token. Sign in with your account instead.', 'scope_forbidden', []);
        }
        if ($area !== 'public') {
            if ($requirement === null) {
                return $this->deny('This endpoint is not available to scoped API tokens.', 'scope_unmapped', []);
            }
            if (!TokenScopes::allows($context->scopes, $area, $requirement['write'])) {
                $needed = $area . ':' . ($requirement['write'] ? 'write' : 'read');

                return $this->deny("This token lacks the {$needed} scope.", 'scope_missing', [$needed]);
            }
        }

        $limit = $context->meta->rate_limit_per_minute;
        if ($limit !== null) {
            $key = 'ulams-token:' . $context->tokenId;
            if (RateLimiter::tooManyAttempts($key, $limit)) {
                return response()->json([
                    'success' => false,
                    'message' => 'This token exceeded its rate limit.',
                    'error' => 'rate_limited',
                ], 429, ['Retry-After' => RateLimiter::availableIn($key)]);
            }
            RateLimiter::hit($key, 60);
        }

        return $next($request);
    }

    /** @param list<string> $required */
    private function deny(string $message, string $error, array $required): Response
    {
        return response()->json(['success' => false, 'message' => $message, 'error' => $error, 'required' => $required], 403);
    }
}
