<?php

namespace Ulams\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ulams\Tenancy\Support\TenantContext;

/**
 * The platform tenant API exists only on the platform, and only when `TENANCY_PLATFORM_API=true`
 * (ADR 0078). Otherwise every route answers like an unknown path, before authentication, so a
 * tenant host or a default installation does not even reveal that it is there.
 */
class EnsurePlatformApi
{
    public function handle(Request $request, Closure $next)
    {
        if (!config('ulams_tenancy.platform_api') || !TenantContext::isPlatform()) {
            return new JsonResponse(['success' => false, 'message' => 'Not found.'], 404);
        }

        return $next($request);
    }
}
