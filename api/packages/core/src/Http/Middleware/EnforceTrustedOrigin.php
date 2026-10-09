<?php

namespace Ulams\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses POST/PUT/PATCH/DELETE whose `Origin` (or, without it, `Sec-Fetch-Site`) says the
 * request was started by a page that is not one of the tenant's own apps. Clients that send
 * neither header (servers, the mobile apps, curl) are not browsers and are let through to the
 * bearer-token authentication. Content origins never match, not even through the localhost
 * patterns of the development stack.
 */
class EnforceTrustedOrigin
{
    private const SAFE = ['GET', 'HEAD', 'OPTIONS'];

    public function handle(Request $request, Closure $next): Response
    {
        if (!config('ulams.core.security.origin_check', true)
            || in_array($request->getMethod(), self::SAFE, true)
            || $request->is(...(array) config('ulams.core.security.origin_exempt', []))
        ) {
            return $next($request);
        }

        $origin = $request->headers->get('Origin');
        if ($origin !== null) {
            if ($this->isTrusted($origin)) {
                return $next($request);
            }

            return $this->refuse();
        }

        $site = $request->headers->get('Sec-Fetch-Site');
        if ($site === null || in_array(strtolower($site), ['same-origin', 'none'], true)) {
            return $next($request);
        }

        return $this->refuse();
    }

    public function isTrusted(string $origin): bool
    {
        $parts = $this->parse($origin);
        if ($parts === null) {
            return false; // "null" (sandboxed frames), garbage
        }
        [$scheme, $host, $port] = $parts;

        if ($this->isContentOrigin($host)) {
            return false;
        }

        foreach ($this->allowList() as $allowed) {
            if ($allowed === [$scheme, $host, $port]) {
                return true;
            }
        }

        return !app()->environment('production')
            && config('ulams.core.security.trust_localhost_outside_production', true)
            && ($host === 'localhost' || str_ends_with($host, '.localhost'));
    }

    /** @return list<array{string, string, int}> */
    private function allowList(): array
    {
        $urls = array_merge([
            config('app.frontend_url'),
            config('ulams.core.security.admin_url'),
            config('app.url'),
        ], (array) config('ulams.core.security.trusted_origins', []));

        $allowed = [];
        foreach ($urls as $url) {
            if (is_string($url) && ($parts = $this->parse($url)) !== null && !$this->isContentOrigin($parts[1])) {
                $allowed[] = $parts;
            }
        }

        return $allowed;
    }

    /** `<slug>.content.<domain>` and `content.<domain>`, and whatever CONTENT_ORIGIN says. */
    private function isContentOrigin(string $host): bool
    {
        if (in_array('content', explode('.', $host), true)) {
            return true;
        }
        $configured = config('ulams_uploads.content_origin') ?: config('scorm.content_origin');
        $parts = is_string($configured) && $configured !== '' ? $this->parse($configured) : null;

        return $parts !== null && $parts[1] === $host;
    }

    /** @return array{string, string, int}|null scheme, host, port */
    private function parse(string $url): ?array
    {
        $p = parse_url(trim($url));
        if (!is_array($p) || empty($p['scheme']) || empty($p['host'])) {
            return null;
        }
        $scheme = strtolower($p['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        return [$scheme, strtolower($p['host']), (int) ($p['port'] ?? ($scheme === 'https' ? 443 : 80))];
    }

    private function refuse(): Response
    {
        return response()->json(['message' => 'Cross-origin request refused'], 403);
    }
}
