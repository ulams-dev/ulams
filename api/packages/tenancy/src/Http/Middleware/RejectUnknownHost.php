<?php

namespace Ulams\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ulams\Tenancy\Services\Contracts\DomainRegistryContract;

/**
 * laravel-multidomain loads the platform `.env` for any host without its own env file, so an
 * unknown subdomain would be served the platform's data. Answer 404 instead, unless the host
 * is a platform host or a provisioned tenant.
 */
class RejectUnknownHost
{
    public function __construct(private DomainRegistryContract $domains)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        if (!config('ulams_tenancy.enforce_known_hosts', true)) {
            return $next($request);
        }

        $host = self::hostOf($request);
        if ($this->isKnown($host)) {
            return $next($request);
        }

        return new JsonResponse(['success' => false, 'message' => 'Unknown host.'], 404);
    }

    public function isKnown(string $host): bool
    {
        return in_array($host, (array) config('ulams_tenancy.platform_hosts', []), true)
            || $this->domains->isRegistered($host);
    }

    /**
     * The host the application used to pick its env file. On the multidomain application
     * that is `app()->domain()`; otherwise it is derived the same way bootstrap/app.php does:
     * X-Forwarded-Host, then Host, without the port. Not normalised on purpose: the env
     * file lookup is case-sensitive too.
     */
    public static function hostOf(Request $request): string
    {
        $app = app();
        if (method_exists($app, 'domain') && !$app->runningUnitTests()) {
            return (string) $app->domain();
        }

        $host = (string) ($request->headers->get('X-Forwarded-Host') ?: $request->headers->get('Host', ''));

        return (string) preg_replace('/:\d+$/', '', $host);
    }
}
