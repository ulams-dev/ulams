<?php

namespace Ulams\Identity\Services\Saml;

use Illuminate\Http\Request;
use OneLogin\Saml2\Utils;

/**
 * Where a SAML request arrived: the tenant API host the browser used. The SP entity id, the ACS and SLO URLs all
 * derive from it (plan 4.2, question 4), so a message made for another tenant's host never validates here.
 *
 * onelogin/php-saml reads "the current URL" from `$_SERVER` and process-wide statics; {@see run()} sets those
 * for one library call and restores them, so nothing depends on the web server's environment (proxies, php -S,
 * queue workers, tests).
 */
final class SamlContext
{
    public function __construct(public readonly string $scheme, public readonly string $host, public readonly int $port)
    {
    }

    public static function fromRequest(Request $request): self
    {
        return new self($request->getScheme(), $request->getHost(), (int) $request->getPort());
    }

    public static function fromUrl(string $url): self
    {
        $parts = parse_url($url) ?: [];
        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));

        return new self($scheme, strtolower((string) ($parts['host'] ?? 'localhost')), (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80)));
    }

    /** scheme://host[:port], the port only when it is not the default of the scheme. */
    public function origin(): string
    {
        $default = $this->scheme === 'https' ? 443 : 80;

        return $this->scheme . '://' . $this->host . ($this->port !== $default && $this->port > 0 ? ':' . $this->port : '');
    }

    public function metadataUrl(string $slug): string
    {
        return $this->origin() . $this->path($slug, 'metadata');
    }

    public function acsUrl(string $slug): string
    {
        return $this->origin() . $this->path($slug, 'acs');
    }

    public function sloUrl(string $slug): string
    {
        return $this->origin() . $this->path($slug, 'slo');
    }

    public function path(string $slug, string $endpoint): string
    {
        return '/api/identity/saml/' . $slug . '/' . $endpoint;
    }

    /**
     * Runs a library call that looks at the current URL, as if the request had arrived at `$path` on this host.
     *
     * @template T
     * @param callable():T $call
     * @return T
     */
    public function run(string $path, callable $call): mixed
    {
        $previous = [$_SERVER['REQUEST_URI'] ?? null, $_SERVER['QUERY_STRING'] ?? null];
        Utils::setBaseURL('');
        Utils::setSelfProtocol($this->scheme);
        Utils::setSelfHost($this->host);
        Utils::setSelfPort($this->port);
        Utils::setProxyVars(false);
        $_SERVER['REQUEST_URI'] = $path;
        $_SERVER['QUERY_STRING'] = '';
        try {
            return $call();
        } finally {
            foreach (['REQUEST_URI', 'QUERY_STRING'] as $i => $key) {
                if ($previous[$i] === null) {
                    unset($_SERVER[$key]);
                } else {
                    $_SERVER[$key] = $previous[$i];
                }
            }
            Utils::setBaseURL('');
        }
    }
}
