<?php

namespace Ulams\Courses\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\ResponseCache\Middlewares\CacheResponse as BaseCacheResponse;
use Symfony\Component\HttpFoundation\Response;
use Ulams\Courses\Support\ResponseCacheTags;

/**
 * `cacheResponse:<tag>[,<tag>...]` on a route: spatie's response cache with tags (see
 * ResponseCacheTags), dropped when the cache store cannot tag.
 */
class CacheResponse extends BaseCacheResponse
{
    public function handle(Request $request, Closure $next, ...$args): Response
    {
        $config = BaseCacheResponse::for(tags: ResponseCacheTags::forRoute($args));

        return parent::handle($request, $next, substr($config, strpos($config, ':') + 1));
    }
}
