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

        if (!(new AccessTokenGuard())->check($credentials, $request)) {
            throw XapiException::unauthorized();
        }

        $request->attributes->set(self::ACCESS_ATTRIBUTE, $access);

        return $next($request);
    }
}
