<?php

namespace Ulams\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Ulams\Auth\Services\PersonalAccessTokenService;

/**
 * Scoped tokens are handed out as `ulams_pat_<jwt>` so secret scanners can recognise them. Passport
 * only understands the bare JWT, so the prefix is removed from `Authorization` before any guard
 * looks at the request (global middleware, runs first).
 */
class StripTokenPrefix
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->headers->get('Authorization');
        if (is_string($header) && preg_match('/^Bearer\s+' . preg_quote(PersonalAccessTokenService::PREFIX, '/') . '(\S+)$/i', $header, $m)) {
            $request->headers->set('Authorization', 'Bearer ' . $m[1]);
        }

        return $next($request);
    }
}
