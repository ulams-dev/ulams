<?php

namespace Ulams\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accepts a caller's `X-Request-Id` (a ULID or UUID, anything else is replaced), puts it in the
 * log context and echoes it in the response, so a failing call can be traced from the CLI or an
 * agent to the server log and the agent audit log (ADR 0074).
 */
class RequestId
{
    public const HEADER = 'X-Request-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $given = $request->headers->get(self::HEADER);
        $id = is_string($given) && self::valid($given) ? $given : (string) Str::ulid();
        $request->attributes->set('ulams.request_id', $id);
        Log::withContext(['request_id' => $id]);

        $response = $next($request);
        $response->headers->set(self::HEADER, $id);

        return $response;
    }

    public static function valid(string $id): bool
    {
        return Str::isUlid($id) || Str::isUuid($id);
    }
}
