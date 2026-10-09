<?php

namespace Ulams\Lti\Platform;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;
use Ulams\Lti\Exceptions\LtiRequestException;
use Ulams\Lti\Models\LtiTool;
use Ulams\Lti\Support\JwksFetcher;

/**
 * Verifies a JWT signed by a registered tool (deep-linking responses, AGS client assertions)
 * with the tool's static public key or its JWKS.
 */
class ToolJwtVerifier
{
    private const ALGORITHMS = ['RS256', 'RS384', 'RS512'];

    public function __construct(private readonly JwksFetcher $jwks)
    {
    }

    /**
     * Reads the unverified `iss` (the tool's client id) to find the registration.
     */
    public static function unverifiedClaims(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new LtiRequestException('Malformed JWT.');
        }
        $claims = json_decode((string) JWT::urlsafeB64Decode($parts[1]), true);
        if (!is_array($claims)) {
            throw new LtiRequestException('Malformed JWT.');
        }

        return $claims;
    }

    /**
     * @return array verified claims
     * @throws LtiRequestException
     */
    public function verify(LtiTool $tool, string $jwt): array
    {
        $parts = explode('.', $jwt);
        $header = count($parts) === 3 ? json_decode((string) JWT::urlsafeB64Decode($parts[0]), true) : null;
        $alg = is_array($header) ? ($header['alg'] ?? null) : null;
        if (!in_array($alg, self::ALGORITHMS, true)) {
            throw new LtiRequestException('Unsupported or missing JWT algorithm.');
        }

        $key = $tool->public_key
            ? new Key($tool->public_key, $alg)
            : ($tool->jwks_url ? $this->jwks->key($tool->jwks_url, $header['kid'] ?? null) : null);
        if ($key === null) {
            throw new LtiRequestException('The tool has no public key or key set URL.');
        }
        if ($key->getAlgorithm() !== $alg) {
            $key = new Key($key->getKeyMaterial(), $alg);
        }

        $leeway = JWT::$leeway;
        JWT::$leeway = 60;
        try {
            return json_decode(json_encode(JWT::decode($jwt, $key)), true);
        } catch (Throwable $e) {
            throw new LtiRequestException('Invalid JWT signature or expiry: ' . $e->getMessage(), 401, 'invalid_client');
        } finally {
            JWT::$leeway = $leeway;
        }
    }
}
