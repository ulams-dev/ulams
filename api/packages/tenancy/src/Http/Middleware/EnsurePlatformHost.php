<?php

namespace Ulams\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ulams\Tenancy\Support\TenantContext;

/**
 * Public platform endpoints (the entry point lookup) exist on a platform host only: on a tenant host they answer
 * like an unknown path. Unlike the platform tenant API they need no switch, because they only read what the
 * learner front must know to serve a host.
 */
class EnsurePlatformHost
{
    public function handle(Request $request, Closure $next)
    {
        if (!TenantContext::isPlatform()) {
            return new JsonResponse(['success' => false, 'message' => 'Not found.'], 404);
        }

        return $next($request);
    }
}
