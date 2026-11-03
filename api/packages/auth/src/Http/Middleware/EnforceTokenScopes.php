<?php

namespace Ulams\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;
use Ulams\Auth\Support\EnforceKey;
use Ulams\Auth\Support\TokenContext;
use Ulams\Auth\Support\TokenScopes;

/**
 * Enforces the scopes of a scoped personal access token (ADR 0074, docs/plans/cli.md 6.2). Only
 * tokens with an `api_token_meta` row are affected; login, LTI and demo tokens behave as before.
 *
 * GET/HEAD need `<area>:read`, everything else `<area>:write`; `*` passes. A route that is not in
 * `resources/token-scopes.php` is denied (fail closed). Scopes only narrow: the user's own
 * permissions are still checked by the route's policies. A token limited to resources (`resource_ids`, for example one saved
 * report) can call only routes that name a resource in the map (`resource` option), and only one of its own.
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

        if ($area !== 'public' && !empty($context->meta->resource_ids) && !$this->withinResources($request, $requirement['resource'] ?? null, $context->meta->resource_ids)) {
            return $this->deny('This token is limited to specific resources and cannot call this endpoint or resource.', 'resource_limited', []);
        }

        // what a limited token is limited to, for the endpoints that list or filter by it (the OData service document)
        $request->attributes->set('ulams.token_resource_ids', $context->meta->resource_ids ?: null);

        $limit = $context->meta->rate_limit_per_minute;
        if ($limit !== null) {
            $key = EnforceKey::for($context->tokenId);
            if (RateLimiter::tooManyAttempts($key, $limit)) {
                $request->attributes->set('ulams.rate_limited', true);

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

    /**
     * A limited token passes on a route that names a resource (`param`, with an optional `prefix` the id carries in the
     * URL) when that resource is one of its own. `optional` lets the routes of the same family that name none (a service
     * document, a metadata document) through; they must filter what they show by the token themselves.
     *
     * @param array{param:string,prefix?:string,optional?:bool}|null $resource
     * @param list<string> $allowed
     */
    private function withinResources(Request $request, ?array $resource, array $allowed): bool
    {
        if ($resource === null) {
            return false;
        }
        $value = $request->route()?->parameter($resource['param']);
        if ($value === null) {
            return (bool) ($resource['optional'] ?? false);
        }
        $prefix = $resource['prefix'] ?? '';
        $value = (string) $value;
        if ($prefix !== '') {
            if (!str_starts_with($value, $prefix)) {
                return false;
            }
            $value = substr($value, strlen($prefix));
        }

        return in_array($value, $allowed, true);
    }

    /** @param list<string> $required */
    private function deny(string $message, string $error, array $required): Response
    {
        return response()->json(['success' => false, 'message' => $message, 'error' => $error, 'required' => $required], 403);
    }
}
