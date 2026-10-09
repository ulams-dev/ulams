<?php

namespace Ulams\Lrs\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Ulams\Lrs\Extensions\AccessTokenGuard;
use Ulams\Lrs\Models\Access;
use Ulams\Lrs\Models\BasicHttpCredentials;
use Ulams\Lrs\Xapi\XapiException;

/**
 * Resolves the access from the `{source}` URL segment and authenticates the request with
 * AccessTokenGuard. The access is available to controllers as the `xapi_access` request
 * attribute.
 */
class AuthenticateXapiAccess
{
    public const ACCESS_ATTRIBUTE = 'xapi_access';

    /** Claims of the cmi5 session token, when that is what authenticated the request. */
    public const SESSION_ATTRIBUTE = 'xapi_session';

    public function handle(Request $request, Closure $next): mixed
    {
        $source = (string) $request->route('source');

        $access = preg_match('/^[0-9a-f-]{36}$/i', $source)
            ? Access::query()->with('client')->where('uuid', strtolower($source))->first()
            : null;

        if (!$access || !$access->isActive()) {
            throw XapiException::unauthorized();
        }

        $credentials = $access->type() === Access::TYPE_BASIC_HTTP
            ? BasicHttpCredentials::query()->find($access->credentials_id)
            : null;

        $guard = new AccessTokenGuard();

        if (!$guard->check($credentials, $request)) {
            throw XapiException::unauthorized();
        }

        if (($session = $guard->session()) !== null) {
            // A session token works on the access it was issued for, nowhere else.
            if ($session['x'] !== strtolower((string) $access->uuid)) {
                throw XapiException::unauthorized();
            }
            $request->attributes->set(self::SESSION_ATTRIBUTE, $session);
        }

        $request->attributes->set(self::ACCESS_ATTRIBUTE, $access);

        return $next($request);
    }
}
