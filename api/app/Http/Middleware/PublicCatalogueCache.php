<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marks anonymous catalogue reads cacheable by shared caches (see config/http_cache.php):
 * `Cache-Control: public, max-age=0, s-maxage=…, stale-while-revalidate=…` and
 * `Vary: Host, Authorization, Accept-Language, X-Locale`, so a cache keys them per tenant host
 * and never serves them to a request with credentials.
 */
class PublicCatalogueCache
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->applies($request, $response)) {
            $response->headers->set('Cache-Control', sprintf(
                'public, max-age=0, s-maxage=%d, stale-while-revalidate=%d',
                max(0, (int) config('http_cache.s_maxage', 60)),
                max(0, (int) config('http_cache.stale_while_revalidate', 300)),
            ));
            $vary = array_filter(array_map('trim', explode(',', (string) $response->headers->get('Vary'))));
            $response->headers->set('Vary', implode(', ', array_unique([...$vary, 'Host', 'Authorization', 'Accept-Language', 'X-Locale'])));
        }

        return $response;
    }

    private function applies(Request $request, Response $response): bool
    {
        return config('http_cache.enabled', false)
            && $request->isMethodCacheable()
            && $response->getStatusCode() === 200
            && !$request->headers->has('Authorization')
            && !$request->query->has('_token')
            && $response->headers->getCookies() === []
            && $request->is(...(array) config('http_cache.paths', []))
            && !$request->is(...((array) config('http_cache.except', []) ?: ['__none__']));
    }
}
