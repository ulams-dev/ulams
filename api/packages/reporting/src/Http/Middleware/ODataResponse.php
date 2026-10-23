<?php

namespace Ulams\Reporting\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Headers of the OData feed: the protocol version on every answer, and, on a 401, the Basic challenge that makes Excel and
 * Power BI ask for credentials (the password is a `reports:read` token, the user name is ignored).
 */
class ODataResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('OData-Version', '4.0');
        if ($response->getStatusCode() === 401) {
            $response->headers->set('WWW-Authenticate', 'Basic realm="ULAMS reports (user name: anything, password: your access token)"');
        }

        return $response;
    }
}
