<?php

namespace Ulams\Lti\Support;

use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use GuzzleHttp\ClientInterface;
use Illuminate\Support\Facades\Cache;
use Throwable;
use Ulams\Lti\Exceptions\LtiRequestException;

/**
 * Fetches and caches the public key set of a tool or platform through {@see SafeHttp}. An
 * unknown `kid` triggers one refetch (the other side may have rotated its keys).
 */
class JwksFetcher
{
    public function __construct(private readonly ?ClientInterface $http = null)
    {
    }

    /**
     * @throws LtiRequestException
     */
    public function key(string $jwksUrl, ?string $kid): Key
    {
        if ($kid === null || $kid === '') {
            throw new LtiRequestException('The JWT header has no kid.');
        }

        $keys = $this->keys($jwksUrl, false);
        if (!isset($keys[$kid])) {
            $keys = $this->keys($jwksUrl, true);
        }
        if (!isset($keys[$kid])) {
            throw new LtiRequestException('No key with kid ' . $kid . ' in ' . $jwksUrl . '.');
        }

        return $keys[$kid];
    }

    /**
     * @return array<string, Key>
     */
    private function keys(string $jwksUrl, bool $refresh): array
    {
        $cacheKey = 'lti_jwks_' . sha1($jwksUrl);
        if ($refresh) {
            Cache::forget($cacheKey);
        }

        $set = Cache::remember($cacheKey, (int) config('ulams_lti.jwks_cache_ttl', 600), function () use ($jwksUrl) {
            try {
                $response = ($this->http ?? SafeHttp::client())->request('GET', $jwksUrl, ['headers' => ['Accept' => 'application/json']]);
            } catch (LtiRequestException $e) {
                throw $e;
            } catch (Throwable $e) {
                throw new LtiRequestException('Could not fetch the key set from ' . $jwksUrl . '.', 502);
            }
            $set = json_decode((string) $response->getBody(), true);
            if (!is_array($set) || !isset($set['keys']) || !is_array($set['keys'])) {
                throw new LtiRequestException('Invalid key set at ' . $jwksUrl . '.', 502);
            }

            return $set;
        });

        try {
            return JWK::parseKeySet($set, 'RS256');
        } catch (Throwable) {
            throw new LtiRequestException('Invalid key set at ' . $jwksUrl . '.', 502);
        }
    }
}
