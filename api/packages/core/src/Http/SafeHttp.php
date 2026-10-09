<?php

namespace Ulams\Core\Http;

use Closure;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * The one HTTP client for URLs that tenants and authors register (LTI endpoints, Git hosts, web
 * pages, ADR 0032). It refuses to be turned against the internal network:
 *
 * - https only (`allow_insecure` lifts it for local development, `insecure_hosts` for named test hosts);
 * - A and AAAA records are resolved and every address must be public: no private, loopback,
 *   link-local (169.254/16 including the metadata address), CGNAT 100.64/10, 0.0.0.0/8, ULA fc00::/7,
 *   multicast, documentation or IPv4-mapped / NAT64 addresses of those;
 * - the checked address is pinned for the connection (CURLOPT_RESOLVE), so DNS cannot change between
 *   the check and the request, and the check runs on every redirect hop;
 * - redirects are off unless a maximum is given, and then never leave the host of the first URL;
 * - the response is cut off while streaming when it exceeds `max_bytes`;
 * - an optional allow-list of hosts per tenant.
 */
final class SafeHttp
{
    /** @var Closure(string):string[]|null replaces DNS in tests */
    private static ?Closure $resolver = null;

    /** @param Closure(string):string[]|null $resolver host => addresses; null restores DNS */
    public static function useResolver(?Closure $resolver): void
    {
        self::$resolver = $resolver;
    }

    /**
     * @param array{allow_insecure?:bool,insecure_hosts?:string[],allowed_hosts?:string[],max_redirects?:int,max_bytes?:int,timeout?:int,connect_timeout?:int,label?:string,exception?:Closure(string):\Throwable} $options
     */
    public static function client(?callable $handler = null, array $options = []): Client
    {
        $stack = HandlerStack::create($handler);
        $firstHost = null;
        $stack->push(static function (callable $next) use ($options, &$firstHost) {
            return static function (RequestInterface $request, array $requestOptions) use ($next, $options, &$firstHost) {
                $uri = $request->getUri();
                $firstHost ??= strtolower($uri->getHost());
                if (($options['max_redirects'] ?? 0) > 0 && strtolower($uri->getHost()) !== $firstHost) {
                    throw self::fail($options, 'The server redirected to another host (' . $uri->getHost() . ').');
                }
                $resolve = self::check((string) $uri, $options);
                if ($resolve !== null) {
                    $requestOptions['curl'][CURLOPT_RESOLVE] = [$resolve];
                }

                return $next($request, $requestOptions);
            };
        }, 'ulams_ssrf_guard');

        $max = (int) ($options['max_bytes'] ?? 0);
        $config = [
            'handler' => $stack,
            'allow_redirects' => ($options['max_redirects'] ?? 0) > 0 ? ['max' => (int) $options['max_redirects'], 'strict' => true, 'protocols' => ['https', 'http']] : false,
            'timeout' => (int) ($options['timeout'] ?? 30),
            'connect_timeout' => (int) ($options['connect_timeout'] ?? 10),
        ];
        if ($max > 0) {
            $config[RequestOptions::ON_HEADERS] = static function (ResponseInterface $response) use ($max, $options) {
                if ((int) $response->getHeaderLine('Content-Length') > $max) {
                    throw self::fail($options, 'The response is larger than ' . $max . ' bytes.');
                }
            };
            $config[RequestOptions::PROGRESS] = static function ($downloadTotal, $downloaded) use ($max, $options) {
                if ($downloaded > $max) {
                    throw self::fail($options, 'The response is larger than ' . $max . ' bytes.');
                }
            };
        }

        return new Client($config);
    }

    /**
     * Validates a URL. Returns a CURLOPT_RESOLVE entry pinning the checked address, or null when the
     * checks are disabled for this host.
     *
     * @param array{allow_insecure?:bool,insecure_hosts?:string[],allowed_hosts?:string[],label?:string,exception?:Closure(string):\Throwable} $options
     * @throws UnsafeUrlException
     */
    public static function check(string $url, array $options = []): ?string
    {
        $label = (string) ($options['label'] ?? 'URLs');
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');
        if ($host === '' || !in_array($scheme, ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass'])) {
            throw self::fail($options, 'Invalid URL: ' . $url);
        }
        $bareHost = strtolower(trim($host, '[]'));
        $allowed = array_map('strtolower', (array) ($options['allowed_hosts'] ?? []));
        if ($allowed !== [] && !in_array($bareHost, $allowed, true)) {
            throw self::fail($options, "{$label} may only use the hosts your admin allowed; {$bareHost} is not one of them.");
        }
        if (!empty($options['allow_insecure']) || in_array($bareHost, array_map('strtolower', (array) ($options['insecure_hosts'] ?? [])), true)) {
            return null;
        }
        if ($scheme !== 'https') {
            throw self::fail($options, "{$label} must use https: {$url}");
        }

        $addresses = filter_var($bareHost, FILTER_VALIDATE_IP) ? [$bareHost] : self::resolve($bareHost);
        if ($addresses === []) {
            throw self::fail($options, 'Cannot resolve ' . $host);
        }
        foreach ($addresses as $address) {
            if (!self::isPublic($address)) {
                throw self::fail($options, "{$label} must not point to private addresses: {$url}");
            }
        }

        $port = (int) ($parts['port'] ?? 443);

        return "{$bareHost}:{$port}:{$addresses[0]}";
    }

    /** The exception to throw: a caller can ask for its own type (LTI keeps LtiRequestException). */
    private static function fail(array $options, string $message): \Throwable
    {
        return isset($options['exception']) ? ($options['exception'])($message) : new UnsafeUrlException($message);
    }

    /** @return string[] */
    private static function resolve(string $host): array
    {
        if (self::$resolver !== null) {
            return array_values((self::$resolver)($host));
        }
        $addresses = [];
        foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
            $addresses[] = $record['ip'] ?? $record['ipv6'] ?? null;
        }
        $addresses = array_values(array_filter($addresses));

        return $addresses !== [] ? $addresses : (gethostbynamel($host) ?: []);
    }

    public static function isPublic(string $address): bool
    {
        $packed = @inet_pton($address);
        if ($packed === false) {
            return false;
        }
        if (strlen($packed) === 4) {
            return self::isPublicV4($address);
        }
        $bytes = array_values(unpack('C*', $packed));
        // IPv4-mapped (::ffff:a.b.c.d) and NAT64 (64:ff9b::/96) carry an IPv4 address: it must be public
        $mapped = array_slice($bytes, 0, 10) === array_fill(0, 10, 0) && $bytes[10] === 0xff && $bytes[11] === 0xff;
        $nat64 = array_slice($bytes, 0, 12) === [0x00, 0x64, 0xff, 0x9b, 0, 0, 0, 0, 0, 0, 0, 0];
        if ($mapped || $nat64) {
            return self::isPublicV4(implode('.', array_slice($bytes, 12, 4)));
        }
        if ($packed === str_repeat("\0", 16) || $packed === str_repeat("\0", 15) . "\1") {
            return false;
        }
        $first = $bytes[0];
        $second = $bytes[1];
        if (($first & 0xfe) === 0xfc || $first === 0xff || ($first === 0xfe && ($second & 0xc0) === 0x80) || ($first === 0xfe && ($second & 0xc0) === 0xc0)) {
            return false; // ULA fc00::/7, multicast, link-local fe80::/10, site-local fec0::/10
        }
        if ($first === 0x20 && $second === 0x01 && $bytes[2] === 0x0d && $bytes[3] === 0xb8) {
            return false; // documentation 2001:db8::/32
        }

        return true;
    }

    private static function isPublicV4(string $address): bool
    {
        if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        [$a, $b] = array_map('intval', explode('.', $address));

        return !($a === 0 || ($a === 100 && $b >= 64 && $b <= 127) || $a >= 224);
    }

    public static function hostOf(UriInterface|string $uri): string
    {
        return strtolower(is_string($uri) ? (string) parse_url($uri, PHP_URL_HOST) : $uri->getHost());
    }
}
