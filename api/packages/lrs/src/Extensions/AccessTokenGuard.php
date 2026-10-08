<?php

namespace Ulams\Lrs\Extensions;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Passport;
use Throwable;
use Ulams\Lrs\Models\BasicHttpCredentials;

/**
 * Authenticates requests to the xAPI endpoints.
 *
 * Accepted `Authorization` values:
 * - `Basic <jwt>` or `Bearer <jwt>`: a Passport access token (this is what cmi5 content gets
 *   from the fetch URL). The RS256 signature is verified with the Passport public key, the
 *   `exp`/`nbf` claims are checked, and the token must exist in the database, not be revoked
 *   and not be expired.
 * - `Basic base64(username:password)`: the access's own HTTP Basic credentials.
 */
class AccessTokenGuard
{
    private ?Authenticatable $user = null;

    public function type(): string
    {
        return 'basic_http';
    }

    public function name(): string
    {
        return 'Basic HTTP';
    }

    /**
     * @param BasicHttpCredentials|null $credentials the access's Basic credentials, if any
     */
    public function check($credentials, Request $request): bool
    {
        $this->user = null;
        $header = (string) $request->header('Authorization', '');

        if (!preg_match('/^(Basic|Bearer)\s+(\S+)$/i', trim($header), $matches)) {
            return false;
        }

        [, $scheme, $value] = $matches;

        if (substr_count($value, '.') === 2) {
            return $this->passportTokenIsValid($value);
        }

        return strcasecmp($scheme, 'Basic') === 0 && $this->basicCredentialsAreValid($value, $credentials);
    }

    /**
     * The user who owns the Passport token of the last successful check.
     */
    public function user(): ?Authenticatable
    {
        return $this->user;
    }

    private function passportTokenIsValid(string $jwt): bool
    {
        $publicKey = $this->passportPublicKey();

        if ($publicKey === null) {
            return false;
        }

        try {
            $claims = JWT::decode($jwt, new Key($publicKey, 'RS256'));
        } catch (Throwable) {
            // Bad signature, wrong algorithm, malformed, expired or not yet valid.
            return false;
        }

        if (!isset($claims->jti) || !is_string($claims->jti)) {
            return false;
        }

        $token = Passport::token()->newQuery()->where('id', $claims->jti)->first();

        if (!$token || $token->revoked || ($token->expires_at && $token->expires_at < Carbon::now())) {
            return false;
        }

        $this->user = $token->user;

        return true;
    }

    private function basicCredentialsAreValid(string $encoded, ?BasicHttpCredentials $credentials): bool
    {
        if (!$credentials) {
            return false;
        }

        $decoded = base64_decode($encoded, true);

        if ($decoded === false || !str_contains($decoded, ':')) {
            return false;
        }

        [$username, $password] = explode(':', $decoded, 2);
        $stored = (string) $credentials->getRawOriginal('password');

        if (!hash_equals((string) $credentials->username, $username)) {
            return false;
        }

        return Hash::info($stored)['algo'] !== null
            ? Hash::check($password, $stored)
            : hash_equals($stored, $password);
    }

    /**
     * Same key resolution as Passport: `passport.public_key` config, else the key file.
     */
    private function passportPublicKey(): ?string
    {
        $key = str_replace('\\n', "\n", (string) config('passport.public_key', ''));

        if ($key !== '') {
            return $key;
        }

        $path = Passport::keyPath('oauth-public.key');

        return is_readable($path) ? (string) file_get_contents($path) : null;
    }
}
