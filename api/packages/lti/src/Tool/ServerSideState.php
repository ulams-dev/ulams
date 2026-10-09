<?php

namespace Ulams\Lti\Tool;

use Packback\Lti1p3\Interfaces\ICookie;
use Ulams\Lti\Services\NonceStore;

/**
 * The library keeps the OIDC `state` in a cookie. Inside an LMS iframe third-party cookies are
 * usually blocked, so the state is kept server-side instead: a random 256-bit value, single use,
 * 10 minutes. The nonce bound to it (ToolCache) still ties the id_token to this login.
 */
class ServerSideState implements ICookie
{
    public function __construct(private readonly NonceStore $nonces)
    {
    }

    public function getCookie(string $name): ?string
    {
        $payload = $this->nonces->take(NonceStore::STATE, $name);

        return $payload === null ? null : (string) ($payload['value'] ?? '');
    }

    public function setCookie(string $name, string $value, int $exp = 3600, array $options = []): void
    {
        $this->nonces->remember(NonceStore::STATE, $name, max($exp, (int) config('ulams_lti.state_ttl', 600)), ['value' => $value]);
    }
}
