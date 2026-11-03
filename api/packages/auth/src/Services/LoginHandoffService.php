<?php

namespace Ulams\Auth\Services;

use Illuminate\Support\Str;
use Ulams\Auth\Exceptions\LoginHandoffException;
use Ulams\Auth\Models\LoginHandoffCode;
use Ulams\Auth\Models\LoginHandoffState;

/**
 * State and one-time codes of browser logins that go through another party (ADR 0121).
 *
 * A login starts with {@see startState()}: the string handed to the other party is signed
 * (HMAC with a key derived from the tenant's APP_KEY) and carries a random nonce, the provider, the
 * tenant API host and an expiry; everything else (allow-listed return URL, the front end's S256
 * challenge, protocol data) stays server-side in a row keyed by the hash of the nonce. When the
 * other party calls back, {@see consumeState()} checks the signature, expiry, provider and host and
 * burns the row, so a state works once. The callback then mints a one-time code with
 * {@see issueCode()}; the front end's server redeems it with {@see redeemCode()} and the secret
 * verifier only it holds (PKCE style), so a code that leaks through a URL, a log or the Referer is
 * useless to anybody else. Codes live 60 seconds and are single use.
 */
class LoginHandoffService
{
    public const STATE_TTL_SECONDS = 600;
    public const CODE_TTL_SECONDS = 60;

    /** Challenge and verifier format: base64url of 32 random bytes (43 characters) or longer, RFC 7636 style. */
    public const CHALLENGE_PATTERN = '/^[A-Za-z0-9_-]{43}$/';
    public const VERIFIER_PATTERN = '/^[A-Za-z0-9._~-]{43,128}$/';

    /**
     * @param array<string, mixed> $data protocol data (OIDC nonce, SAML request id, ...), stored encrypted
     * @return string the signed state to give to the other party
     */
    public function startState(
        string $protocol,
        string $provider,
        string $host,
        string $returnTo,
        string $challenge,
        array $data = []
    ): string {
        $nonce = self::base64Url(random_bytes(32));
        $expires = now()->addSeconds(self::STATE_TTL_SECONDS);

        LoginHandoffState::query()->create([
            'state_hash' => hash('sha256', $nonce),
            'protocol' => $protocol,
            'provider' => $provider,
            'host' => strtolower($host),
            'return_to' => $returnTo,
            'challenge' => $challenge,
            'data' => $data,
            'expires_at' => $expires,
        ]);

        $exp = (string) $expires->getTimestamp();

        return $nonce . '.' . $exp . '.' . $this->sign($protocol, $provider, $host, $nonce, $exp);
    }

    /**
     * Validates and burns a state.
     *
     * @throws LoginHandoffException
     */
    public function consumeState(?string $state, string $protocol, string $provider, string $host): LoginHandoffState
    {
        $parts = is_string($state) ? explode('.', $state) : [];
        if (count($parts) !== 3 || strlen((string) $state) > 256 || !preg_match('/^[A-Za-z0-9_-]{43}$/', $parts[0])
            || !ctype_digit($parts[1]) || !preg_match('/^[A-Za-z0-9_-]{43}$/', $parts[2])) {
            throw new LoginHandoffException(LoginHandoffException::MALFORMED);
        }
        [$nonce, $exp, $signature] = $parts;

        // Signed over protocol, provider and host: a state started for another provider or tenant
        // fails here, before any lookup.
        if (!hash_equals($this->sign($protocol, $provider, $host, $nonce, $exp), $signature)) {
            throw new LoginHandoffException(LoginHandoffException::BAD_SIGNATURE);
        }
        if ((int) $exp < now()->getTimestamp()) {
            throw new LoginHandoffException(LoginHandoffException::EXPIRED);
        }

        /** @var LoginHandoffState|null $row */
        $row = LoginHandoffState::query()->where('state_hash', hash('sha256', $nonce))->first();
        if (!$row) {
            throw new LoginHandoffException(LoginHandoffException::UNKNOWN);
        }
        if ($row->expires_at->isPast()) {
            throw new LoginHandoffException(LoginHandoffException::EXPIRED);
        }
        if ($row->protocol !== $protocol || $row->provider !== $provider) {
            throw new LoginHandoffException(LoginHandoffException::WRONG_PROVIDER);
        }
        if ($row->host !== strtolower($host)) {
            throw new LoginHandoffException(LoginHandoffException::WRONG_HOST);
        }

        // single use: only the request that flips the flag wins
        $won = LoginHandoffState::query()->whereKey($row->getKey())->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);
        if ($won !== 1) {
            throw new LoginHandoffException(LoginHandoffException::REPLAYED);
        }

        return $row;
    }

    /** @return string the one-time code for the redirect back to the front end */
    public function issueCode(
        LoginHandoffState $state,
        string $kind,
        ?int $userId = null,
        ?string $payload = null,
        bool $rememberMe = false
    ): string {
        $code = self::base64Url(random_bytes(32));

        LoginHandoffCode::query()->create([
            'code_hash' => hash('sha256', $code),
            'kind' => $kind,
            'host' => $state->host,
            'user_id' => $userId,
            'challenge' => $state->challenge,
            'remember_me' => $rememberMe,
            'payload' => $payload,
            'expires_at' => now()->addSeconds(self::CODE_TTL_SECONDS),
        ]);

        return $code;
    }

    /**
     * Redeems a code. The row is burned before the verifier is looked at, so guessing verifiers
     * against a stolen code is not possible.
     *
     * @throws LoginHandoffException
     */
    public function redeemCode(?string $code, ?string $verifier, string $host): LoginHandoffCode
    {
        if (!is_string($code) || !preg_match('/^[A-Za-z0-9_-]{43}$/', $code)
            || !is_string($verifier) || !preg_match(self::VERIFIER_PATTERN, $verifier)) {
            throw new LoginHandoffException(LoginHandoffException::MALFORMED);
        }

        /** @var LoginHandoffCode|null $row */
        $row = LoginHandoffCode::query()->where('code_hash', hash('sha256', $code))->first();
        if (!$row) {
            throw new LoginHandoffException(LoginHandoffException::UNKNOWN);
        }

        $won = LoginHandoffCode::query()->whereKey($row->getKey())->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->update(['consumed_at' => now()]);
        if ($won !== 1) {
            throw new LoginHandoffException($row->consumed_at ? LoginHandoffException::REPLAYED : LoginHandoffException::EXPIRED);
        }

        if ($row->host !== strtolower($host)) {
            throw new LoginHandoffException(LoginHandoffException::WRONG_HOST);
        }
        if (!hash_equals($row->challenge, self::challengeFor($verifier))) {
            throw new LoginHandoffException(LoginHandoffException::BAD_VERIFIER);
        }

        return $row;
    }

    /** S256: base64url(sha256(verifier)). */
    public static function challengeFor(string $verifier): string
    {
        return self::base64Url(hash('sha256', $verifier, true));
    }

    /** Deletes states and codes that expired more than a day ago. */
    public function prune(): int
    {
        $before = now()->subDay();

        return LoginHandoffState::query()->where('expires_at', '<', $before)->delete()
            + LoginHandoffCode::query()->where('expires_at', '<', $before)->delete();
    }

    private function sign(string $protocol, string $provider, string $host, string $nonce, string $exp): string
    {
        $message = implode('|', ['v1', $protocol, $provider, strtolower($host), $nonce, $exp]);

        return self::base64Url(hash_hmac('sha256', $message, $this->key(), true));
    }

    private function key(): string
    {
        $appKey = (string) config('app.key');
        if (Str::startsWith($appKey, 'base64:')) {
            $appKey = base64_decode(substr($appKey, 7), true) ?: $appKey;
        }

        return hash_hmac('sha256', 'ulams.login-handoff.v1', $appKey, true);
    }

    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
