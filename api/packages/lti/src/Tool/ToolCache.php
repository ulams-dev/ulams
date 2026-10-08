<?php

namespace Ulams\Lti\Tool;

use Illuminate\Support\Facades\Cache;
use Packback\Lti1p3\Interfaces\ICache;
use Ulams\Lti\Services\NonceStore;

/**
 * Launch data and nonces in the tenant database (single use); platform access tokens in the
 * tenant cache.
 */
class ToolCache implements ICache
{
    public function __construct(private readonly NonceStore $nonces)
    {
    }

    public function getLaunchData(string $key): ?array
    {
        return $this->nonces->peek(NonceStore::LAUNCH, $key);
    }

    public function cacheLaunchData(string $key, array $jwtBody): void
    {
        $this->nonces->remember(NonceStore::LAUNCH, $key, 3600, $jwtBody);
    }

    public function cacheNonce(string $nonce, string $state): void
    {
        $this->nonces->remember(NonceStore::NONCE, $nonce, (int) config('ulams_lti.state_ttl', 600), ['state' => $state]);
    }

    public function checkNonceIsValid(string $nonce, string $state): bool
    {
        $payload = $this->nonces->take(NonceStore::NONCE, $nonce);

        return $payload !== null && hash_equals((string) ($payload['state'] ?? ''), $state);
    }

    public function cacheAccessToken(string $key, string $accessToken): void
    {
        Cache::put('lti_platform_token_' . sha1($key), encrypt($accessToken), 3300);
    }

    public function getAccessToken(string $key): ?string
    {
        $value = Cache::get('lti_platform_token_' . sha1($key));

        return $value === null ? null : decrypt($value);
    }

    public function clearAccessToken(string $key): void
    {
        Cache::forget('lti_platform_token_' . sha1($key));
    }
}
