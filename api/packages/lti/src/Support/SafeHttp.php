<?php

namespace Ulams\Lti\Support;

use GuzzleHttp\Client;
use Ulams\Core\Http\SafeHttp as CoreSafeHttp;
use Ulams\Core\Http\UnsafeUrlException;
use Ulams\Lti\Exceptions\LtiRequestException;

/**
 * HTTP client for URLs that tenants register (tool and platform JWKS, token and AGS endpoints). A thin
 * wrapper over the shared {@see CoreSafeHttp} (ADR 0032) that keeps the LTI exception type and the
 * `ulams_lti` config keys: https only, no redirects, public addresses only, address pinned.
 * `LTI_ALLOW_INSECURE_URLS=true` lifts the checks for local development.
 */
class SafeHttp
{
    private static function options(): array
    {
        return [
            'allow_insecure' => (bool) config('ulams_lti.allow_insecure_urls'),
            'timeout' => (int) config('ulams_lti.http_timeout', 10),
            'connect_timeout' => (int) config('ulams_lti.http_timeout', 10),
            'label' => 'LTI endpoints',
            'exception' => static fn (string $message) => new LtiRequestException($message, 502),
        ];
    }

    public static function client(?callable $handler = null): Client
    {
        return CoreSafeHttp::client($handler, self::options());
    }

    /**
     * Validates a URL. Returns a CURLOPT_RESOLVE entry pinning the checked address, or null when
     * the checks are disabled.
     *
     * @throws LtiRequestException
     */
    public static function check(string $url): ?string
    {
        try {
            return CoreSafeHttp::check($url, self::options());
        } catch (UnsafeUrlException $e) {
            throw new LtiRequestException($e->getMessage(), 502);
        }
    }
}
