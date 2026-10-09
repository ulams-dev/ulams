<?php

namespace Ulams\Lti\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * AGS calls carry `Authorization: Bearer <our AGS access token>`. Global middleware resolves the
 * Passport user on every request, and Passport blanks (and reports) any bearer header that is not
 * one of its tokens. This middleware runs first: on AGS paths it moves the header aside, so
 * Passport never sees it and the AGS controller reads it with {@see token()}.
 */
class IsolateLtiBearer
{
    private const ATTRIBUTE = 'ulams_lti_bearer';

    public function handle(Request $request, Closure $next)
    {
        if ($request->is('api/lti/platform/ags/*')) {
            $header = (string) $request->headers->get('Authorization', '');
            if (stripos($header, 'Bearer ') === 0) {
                $request->attributes->set(self::ATTRIBUTE, trim(substr($header, 7)));
            }
            $request->headers->remove('Authorization');
            $request->server->remove('HTTP_AUTHORIZATION');
        }

        return $next($request);
    }

    public static function token(Request $request): ?string
    {
        $token = $request->attributes->get(self::ATTRIBUTE);

        return is_string($token) && $token !== '' ? $token : null;
    }
}
