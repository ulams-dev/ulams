<?php

namespace Ulams\Core\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * JSON answers of the API are for the tenant's own apps. `Cross-Origin-Resource-Policy:
 * same-origin` stops a page of another origin (the content origin runs third-party package
 * code) from loading them in no-cors mode. It does not affect `fetch` in cors mode, which the
 * admin and front SPAs use and which is governed by the CORS headers instead. Files (images,
 * PDFs, package files) are not JSON responses and keep their own policy.
 */
class ProtectJsonResponses
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof JsonResponse) {
            if (!$response->headers->has('Cross-Origin-Resource-Policy')) {
                $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
            }
            $response->headers->set('X-Content-Type-Options', 'nosniff');
        }

        return $response;
    }
}
