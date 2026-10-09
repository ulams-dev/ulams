<?php

namespace Ulams\Interactive\Services;

use Ulams\Interactive\Models\InteractivePackage;
use Ulams\Interactive\Models\InteractivePackageVersion;
use Ulams\Uploads\Http\ContentHeaderProvider;

/**
 * The Content-Security-Policy of one package version (ADR 0086), set by the API for every file under
 * interactive/ on the content origin. No 'unsafe-eval'; the network is the package's own files only,
 * unless the tenant allows it (`ulams_interactive.allow_network`) and the manifest lists the origins.
 */
class InteractiveCsp implements ContentHeaderProvider
{
    public static function for(InteractivePackageVersion $version): string
    {
        $connect = "'self'";
        if (config('ulams_interactive.allow_network')) {
            foreach ((array) ($version->manifest['network'] ?? []) as $origin) {
                if (is_string($origin) && preg_match('#^https://[a-z0-9]([a-z0-9.-]*[a-z0-9])?(:[0-9]{1,5})?$#', $origin)) {
                    $connect .= ' ' . $origin;
                }
            }
        }

        $directives = [
            "default-src 'none'",
            "script-src 'self' 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "media-src 'self' blob:",
            "font-src 'self' data:",
            'connect-src ' . $connect,
            "worker-src 'self' blob:",
            "frame-src 'none'",
            'frame-ancestors ' . self::frameAncestors(),
            "form-action 'none'",
            "base-uri 'none'",
            "object-src 'none'",
        ];
        $api = rtrim((string) config('app.url'), '/');
        if ($api !== '') {
            $directives[] = 'report-uri ' . $api . '/api/csp-report';
        }

        return implode('; ', $directives);
    }

    /**
     * The tenant's learner front and admin (and the extra first-party origins in TRUSTED_ORIGINS): the only
     * pages that may frame a package. Outside production, `localhost` and `*.localhost` hosts without a port
     * get `:*`, because the dev front runs on its own port (:4321), like the trusted-origin check does.
     */
    public static function frameAncestors(): string
    {
        $local = !app()->environment('production') && config('ulams.core.security.trust_localhost_outside_production', false);
        $origins = [];
        $urls = [config('app.frontend_url'), config('ulams.core.security.admin_url'), ...(array) config('ulams.core.security.trusted_origins', [])];
        foreach ($urls as $url) {
            $parts = parse_url((string) $url);
            if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) || !in_array($parts['scheme'], ['http', 'https'], true)) {
                continue;
            }
            if (!preg_match('/^[A-Za-z0-9.-]+$/', $parts['host'])) {
                continue;
            }
            $port = isset($parts['port']) ? ':' . $parts['port'] : ($local && ($parts['host'] === 'localhost' || str_ends_with($parts['host'], '.localhost')) ? ':*' : '');
            $origins[] = $parts['scheme'] . '://' . $parts['host'] . $port;
        }

        return $origins === [] ? "'none'" : implode(' ', array_values(array_unique($origins)));
    }

    public function headersFor(string $path): ?array
    {
        // interactive/<storage_key>/v<version>/<file>
        if (!preg_match('#^interactive/([0-9a-f-]{36})/v([0-9]+)/#', $path, $m)) {
            return null;
        }
        $version = InteractivePackageVersion::query()
            ->whereHas('package', fn ($q) => $q->where('storage_key', $m[1]))
            ->where('version', (int) $m[2])
            ->first();
        if ($version === null) {
            return null;
        }

        return [
            'Content-Security-Policy' => self::for($version),
            'Referrer-Policy' => 'no-referrer',
            // immutable: a version's files never change
            'Cache-Control' => 'public, max-age=3600',
        ];
    }
}
