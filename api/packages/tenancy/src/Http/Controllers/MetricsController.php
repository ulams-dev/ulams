<?php

namespace Ulams\Tenancy\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\IpUtils;
use Ulams\Core\Observability\MetricsRegistry;
use Ulams\Core\Observability\PrometheusText;
use Ulams\Tenancy\Support\TenantContext;

/**
 * `GET /metrics` for Prometheus: the platform host only, and only for scrapers on a private network or
 * with the bearer token (`METRICS_ALLOWED_CIDRS`, `METRICS_TOKEN`). Everyone else gets the 404 of an
 * unknown path. The gateway does not route the path on public hosts (docs/operators/monitoring).
 */
class MetricsController
{
    public function __invoke(Request $request, MetricsRegistry $registry): Response
    {
        if (!TenantContext::isPlatform() || !self::allowed($request)) {
            return response('Not found.', 404)->header('Content-Type', 'text/plain');
        }

        return response($registry->render(), 200, [
            'Content-Type' => PrometheusText::CONTENT_TYPE,
            'Cache-Control' => 'no-store',
        ]);
    }

    public static function allowed(Request $request): bool
    {
        $token = (string) config('ulams.core.metrics.token');
        // the server bag, not the header bag: Passport blanks the header of a token it cannot validate, and the
        // scrape token is not a JWT
        $header = (string) ($request->server->get('HTTP_AUTHORIZATION') ?: $request->server->get('REDIRECT_HTTP_AUTHORIZATION'));
        $given = preg_match('/^Bearer\s+(\S+)$/i', $header, $m) === 1 ? $m[1] : '';
        if ($token !== '' && $given !== '' && hash_equals($token, $given)) {
            return true;
        }

        // behind a gateway the address of the real client matters, not the gateway's: a request that was
        // forwarded from outside carries one of these headers and must present the token instead
        if ($request->headers->has('CF-Connecting-IP') || $request->headers->has('X-Forwarded-For')) {
            return false;
        }

        $ip = $request->ip();

        return $ip !== null && IpUtils::checkIp($ip, (array) config('ulams.core.metrics.allowed_cidrs', []));
    }
}
