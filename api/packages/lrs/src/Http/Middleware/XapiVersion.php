<?php

namespace Ulams\Lrs\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Ulams\Lrs\Enums\XApiEnum;
use Ulams\Lrs\Xapi\XapiException;

/**
 * Requires a 1.0.x `X-Experience-API-Version` request header and sets it on the response.
 */
class XapiVersion
{
    public function handle(Request $request, Closure $next): mixed
    {
        $version = $request->header('X-Experience-API-Version');

        if ($version === null) {
            throw XapiException::badRequest('Missing X-Experience-API-Version header.');
        }
        if (!preg_match('/^1\.0(\.\d+)?$/', $version)) {
            throw XapiException::badRequest("Unsupported X-Experience-API-Version: [$version].");
        }

        $response = $next($request);
        $response->headers->set('X-Experience-API-Version', XApiEnum::API_VERSION);

        return $response;
    }
}
