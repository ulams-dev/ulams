<?php

namespace Ulams\Auth\Support;

use Ulams\Core\Http\Middleware\EnforceTrustedOrigin;

/**
 * Where a login may send the browser back to: the tenant's own apps (FRONTEND_URL, ADMIN_URL,
 * APP_URL and TRUSTED_ORIGINS, plus localhost outside production), compared by exact origin, the
 * same list the origin check of the API uses. Content origins never match. Reused by every
 * redirect-based login (social, OIDC, SAML).
 */
class ReturnUrlPolicy
{
    public function __construct(private readonly EnforceTrustedOrigin $origins)
    {
    }

    public function isAllowed(?string $url): bool
    {
        if (!is_string($url) || $url === '' || strlen($url) > 2048) {
            return false;
        }
        // Browsers treat a backslash like a slash and ignore tabs and newlines inside a URL, so
        // parsers and browsers can disagree about the host: refuse the whole class.
        if (preg_match('/[\x00-\x20\x7f\\\\]/', $url)) {
            return false;
        }
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $origin = strtolower($parts['scheme']) . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');

        return $this->origins->isTrusted($origin);
    }

    /** The default landing page of the tenant's front end for a login. */
    public function default(): string
    {
        return rtrim((string) config('app.frontend_url'), '/') . '/authentication';
    }
}
