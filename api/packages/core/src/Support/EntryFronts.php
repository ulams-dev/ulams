<?php

namespace Ulams\Core\Support;

use Closure;

/**
 * The extra front hosts of this academy (entry points, ADR 0195): a registry that packages add sources to and the
 * trust decisions read from. The origin check of the API, the return URLs of the redirect logins, the frames that may
 * embed interactive content and the session hand-off between hosts all ask here, so an entry host is trusted
 * everywhere or nowhere, and disabling one removes it from all of them at once.
 *
 * Core knows nothing about where the hosts come from: the tenancy package adds a source that reads the academy's
 * published list. Without a source the list is empty, which is the behaviour of an academy without entry points.
 */
class EntryFronts
{
    /** @var list<Closure(): iterable<string>> */
    private array $sources = [];

    /** @var list<string>|null */
    private ?array $memo = null;

    /** @param Closure(): iterable<string> $source origins (`https://sales.example.org`) of active entry hosts */
    public function addSource(Closure $source): void
    {
        $this->sources[] = $source;
        $this->memo = null;
    }

    /** The origins (scheme, host, port) of the active entry hosts; read once per request. @return list<string> */
    public function origins(): array
    {
        if ($this->memo === null) {
            $origins = [];
            foreach ($this->sources as $source) {
                foreach ($source() as $origin) {
                    if (is_string($origin) && $origin !== '') {
                        $origins[] = $origin;
                    }
                }
            }
            $this->memo = array_values(array_unique($origins));
        }

        return $this->memo;
    }

    /** Names (no port) of the hosts a session may be handed to: the academy's own front and every active entry host. @return list<string> */
    public function frontHosts(): array
    {
        $hosts = [];
        foreach ([config('app.frontend_url'), ...$this->origins()] as $url) {
            $host = is_string($url) ? parse_url($url, PHP_URL_HOST) : null;
            if (is_string($host) && $host !== '') {
                $hosts[] = strtolower($host);
            }
        }

        return array_values(array_unique($hosts));
    }

    public function isFrontHost(string $host): bool
    {
        return in_array(strtolower(trim($host)), $this->frontHosts(), true);
    }

    /** Forgets what was read (tests, and a long-lived worker after the list changed). */
    public function forget(): void
    {
        $this->memo = null;
    }
}
