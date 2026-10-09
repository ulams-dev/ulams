<?php

namespace Ulams\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ulams\Tenancy\Support\TenantContext;

/** The platform API exists only when it is switched on and only on platform hosts (ADR 0078). */
class PlatformApiOnly
{
    public function handle(Request $request, Closure $next)
    {
        if (!config('ulams_tenancy.platform_api', false) || !TenantContext::isPlatform()) {
            return new JsonResponse(['success' => false, 'message' => 'Not found.'], 404);
        }

        return $next($request);
    }
}
