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
 *
 * The OData feed (`api/reporting/odata*`) is read by Excel and Power BI, which can only send a user name and a password
 * (HTTP Basic). There the password is the token and the user name is ignored: it becomes the bearer token here.
 */
class StripTokenPrefix
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->headers->get('Authorization');
        if (is_string($header) && preg_match('/^Bearer\s+' . preg_quote(PersonalAccessTokenService::PREFIX, '/') . '(\S+)$/i', $header, $m)) {
            $request->headers->set('Authorization', 'Bearer ' . $m[1]);
        }

        if ($request->is('api/reporting/odata', 'api/reporting/odata/*') && is_string($header) && preg_match('/^Basic\s+(\S+)$/i', $header, $m)) {
            $decoded = base64_decode($m[1], true);
            $password = is_string($decoded) && str_contains($decoded, ':') ? substr($decoded, strpos($decoded, ':') + 1) : '';
            if (str_starts_with($password, PersonalAccessTokenService::PREFIX)) {
                $request->headers->set('Authorization', 'Bearer ' . substr($password, strlen(PersonalAccessTokenService::PREFIX)));
            }
        }

        return $next($request);
    }
}
