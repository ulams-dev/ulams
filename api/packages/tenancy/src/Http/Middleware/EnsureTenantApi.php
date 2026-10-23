<?php

namespace Ulams\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ulams\Tenancy\Support\TenantContext;

/**
 * Routes of one academy's own settings exist on a tenant host only: on a platform host they answer like an
 * unknown path. The tenant is the one the process was started for (TENANT_SLUG), never anything the
 * request says.
 */
class EnsureTenantApi
{
    public function handle(Request $request, Closure $next)
    {
        if (TenantContext::isPlatform()) {
            return new JsonResponse(['success' => false, 'message' => 'Not found.'], 404);
        }

        return $next($request);
    }
}
