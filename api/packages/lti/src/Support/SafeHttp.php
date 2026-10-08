<?php

namespace Ulams\Lti\Support;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Psr\Http\Message\RequestInterface;
use Ulams\Lti\Exceptions\LtiRequestException;

/**
 * HTTP client for URLs that tenants register (tool and platform JWKS, token and AGS endpoints):
 * https only, no redirects, and the host must resolve to public addresses only. The resolved
 * address is pinned for the connection (CURLOPT_RESOLVE), so DNS cannot change between the check
 * and the request. `LTI_ALLOW_INSECURE_URLS=true` lifts the checks for local development.
 */
class SafeHttp
{
    public static function client(?callable $handler = null): Client
    {
        $stack = HandlerStack::create($handler);
        $stack->push(static function (callable $next) {
            return static function (RequestInterface $request, array $options) use ($next) {
                $resolve = self::check((string) $request->getUri());
                if ($resolve !== null) {
                    $options['curl'][CURLOPT_RESOLVE] = [$resolve];
                }

                return $next($request, $options);
            };
        }, 'ulams_ssrf_guard');

        return new Client([
            'handler' => $stack,
            'allow_redirects' => false,
            'timeout' => (int) config('ulams_lti.http_timeout', 10),
            'connect_timeout' => (int) config('ulams_lti.http_timeout', 10),
        ]);
    }

    /**
     * Validates a URL. Returns a CURLOPT_RESOLVE entry pinning the checked address, or null when
     * the checks are disabled.
     *
     * @throws LtiRequestException
     */
    public static function check(string $url): ?string
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');
        if ($host === '' || !in_array($scheme, ['http', 'https'], true)) {
            throw new LtiRequestException('Invalid URL: ' . $url, 502);
        }
        if (config('ulams_lti.allow_insecure_urls')) {
            return null;
        }
        if ($scheme !== 'https') {
            throw new LtiRequestException('LTI endpoints must use https: ' . $url, 502);
        }

        $bareHost = trim($host, '[]');
        $addresses = filter_var($bareHost, FILTER_VALIDATE_IP) ? [$bareHost] : (gethostbynamel($bareHost) ?: []);
        if ($addresses === []) {
            throw new LtiRequestException('Cannot resolve ' . $host, 502);
        }
        foreach ($addresses as $address) {
            if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new LtiRequestException('LTI endpoints must not point to private addresses: ' . $url, 502);
            }
        }

        $port = (int) ($parts['port'] ?? 443);

        return "{$bareHost}:{$port}:{$addresses[0]}";
    }
}
