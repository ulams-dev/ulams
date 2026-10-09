<?php

namespace Ulams\Auth\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Laravel\Passport\Token;
use Ulams\Auth\Models\DeviceAuthorization;
use Ulams\Auth\Models\User;
use Ulams\Auth\Services\Contracts\DeviceAuthorizationServiceContract;
use Ulams\Auth\Services\Contracts\PersonalAccessTokenServiceContract;
use Ulams\Auth\Support\TokenScopes;

/**
 * Own RFC 8628 device flow (ADR 0075). Only keyed hashes of the codes are stored (HMAC with the
 * tenant's APP_KEY, so a code made on one tenant is unknown on every other). Codes live ten minutes.
 */
class DeviceAuthorizationService implements DeviceAuthorizationServiceContract
{
    public const TTL_SECONDS = 600;

    public const INTERVAL_SECONDS = 5;

    /** Polling faster than this (network jitter allowed) is answered with slow_down. */
    private const MIN_POLL_GAP_SECONDS = 4;

    /** No vowels, no 0/1/I/O look-alikes: `XXXX-XXXX` is read aloud and typed by hand. */
    private const ALPHABET = 'BCDFGHJKLMNPQRSTVWXZ';

    public function __construct(private PersonalAccessTokenServiceContract $tokens)
    {
    }

    public function start(string $clientName, array $scopes, ?string $agentName, ?string $ip, ?string $userAgent): array
    {
        $scopes = TokenScopes::normalize($scopes);
        if (TokenScopes::hasPlatformScope($scopes) && !TokenScopes::isPlatformHost()) {
            throw new InvalidArgumentException('Platform scopes can only be requested on a platform host.');
        }
        $deviceCode = bin2hex(random_bytes(32));
        do {
            $userCode = $this->newUserCode();
        } while (DeviceAuthorization::query()->where('user_code_hash', $this->hash($userCode))->where('expires_at', '>', now())->exists());

        $authorization = DeviceAuthorization::query()->create([
            'device_code_hash' => $this->hash($deviceCode),
            'user_code_hash' => $this->hash($userCode),
            'client_name' => mb_substr(trim(strip_tags($clientName)), 0, 100),
            'agent_name' => $agentName !== null ? mb_substr(trim(strip_tags($agentName)), 0, 100) : null,
            'requested_scopes' => $scopes,
            'status' => DeviceAuthorization::PENDING,
            'ip' => $ip,
            'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
            'expires_at' => now()->addSeconds(self::TTL_SECONDS),
        ]);

        return ['device_code' => $deviceCode, 'user_code' => substr($userCode, 0, 4) . '-' . substr($userCode, 4), 'authorization' => $authorization];
    }

    public function findPendingByUserCode(string $userCode): ?DeviceAuthorization
    {
        $normalized = self::normalizeUserCode($userCode);
        if (strlen($normalized) !== 8) {
            return null;
        }

        return DeviceAuthorization::query()
            ->where('user_code_hash', $this->hash($normalized))
            ->where('status', DeviceAuthorization::PENDING)
            ->where('expires_at', '>', now())
            ->first();
    }

    public function approve(DeviceAuthorization $authorization, User $approver, array $scopes, int $expiresInDays): DeviceAuthorization
    {
        $approved = $this->intersect($authorization->requested_scopes, TokenScopes::normalize($scopes));
        if ($approved === []) {
            throw new InvalidArgumentException('None of the approved scopes was requested by the client.');
        }

        return DB::transaction(function () use ($authorization, $approver, $approved, $expiresInDays) {
            /** @var DeviceAuthorization $row */
            $row = DeviceAuthorization::query()->lockForUpdate()->findOrFail($authorization->getKey());
            if (!$row->isPending()) {
                throw new InvalidArgumentException('This request is no longer pending.');
            }
            $issued = $this->tokens->issue(
                $approver,
                $row->client_name,
                $approved,
                $expiresInDays,
                $row->agent_name !== null ? 'agent' : 'cli',
                $row->agent_name,
                'device',
            );
            $row->forceFill([
                'status' => DeviceAuthorization::APPROVED,
                'user_id' => $approver->getKey(),
                'token_id' => $issued->token->getKey(),
                'approved_scopes' => $approved,
                'access_token_encrypted' => Crypt::encryptString($issued->secret),
            ])->save();

            return $row;
        });
    }

    public function deny(DeviceAuthorization $authorization): DeviceAuthorization
    {
        return DB::transaction(function () use ($authorization) {
            $row = DeviceAuthorization::query()->lockForUpdate()->findOrFail($authorization->getKey());
            if (!$row->isPending()) {
                throw new InvalidArgumentException('This request is no longer pending.');
            }
            $row->forceFill(['status' => DeviceAuthorization::DENIED])->save();

            return $row;
        });
    }

    public function poll(string $deviceCode): array
    {
        return DB::transaction(function () use ($deviceCode) {
            /** @var DeviceAuthorization|null $row */
            $row = DeviceAuthorization::query()->lockForUpdate()->where('device_code_hash', $this->hash($deviceCode))->first();
            if ($row === null || $row->status === DeviceAuthorization::CONSUMED || $row->status === DeviceAuthorization::EXPIRED) {
                return ['error' => 'expired_token'];
            }
            if ($row->isExpired() && $row->status !== DeviceAuthorization::APPROVED) {
                return ['error' => 'expired_token'];
            }
            if ($row->status === DeviceAuthorization::DENIED) {
                return ['error' => 'access_denied'];
            }

            $tooFast = $row->last_polled_at !== null && $row->last_polled_at->diffInSeconds(now(), true) < self::MIN_POLL_GAP_SECONDS;
            $row->forceFill(['last_polled_at' => now()]);

            if ($row->status === DeviceAuthorization::PENDING) {
                $row->save();

                return ['error' => $tooFast ? 'slow_down' : 'authorization_pending'];
            }

            // approved: deliver the token exactly once, unless the code expired before it was collected
            if ($row->isExpired()) {
                $this->discard($row);

                return ['error' => 'expired_token'];
            }
            $secret = Crypt::decryptString((string) $row->access_token_encrypted);
            $token = Token::query()->find($row->token_id);
            $row->forceFill(['status' => DeviceAuthorization::CONSUMED, 'access_token_encrypted' => null])->save();

            return [
                'access_token' => $secret,
                'token_type' => 'Bearer',
                'expires_at' => $token?->expires_at?->toIso8601String(),
                'scopes' => array_values((array) $row->approved_scopes),
                'token_id' => (string) $row->token_id,
            ];
        });
    }

    public function prune(): array
    {
        $revoked = 0;
        DeviceAuthorization::query()->where('status', DeviceAuthorization::APPROVED)->where('expires_at', '<', now())
            ->each(function (DeviceAuthorization $row) use (&$revoked) {
                $this->discard($row);
                $revoked++;
            });
        DeviceAuthorization::query()->where('status', DeviceAuthorization::PENDING)->where('expires_at', '<', now())
            ->update(['status' => DeviceAuthorization::EXPIRED]);
        $deleted = DeviceAuthorization::query()->where('expires_at', '<', now()->subDay())->delete();

        return ['revoked' => $revoked, 'deleted' => $deleted];
    }

    public static function normalizeUserCode(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z]/', '', $code) ?? '');
    }

    /** An approved token nobody collected: revoke it and clear the stored secret. */
    private function discard(DeviceAuthorization $row): void
    {
        if ($row->token_id !== null) {
            Token::query()->whereKey($row->token_id)->update(['revoked' => true]);
        }
        $row->forceFill(['status' => DeviceAuthorization::EXPIRED, 'access_token_encrypted' => null])->save();
    }

    /**
     * Scopes of `$approved` that the client requested: a scope survives when the requested list
     * covers it (`courses:write` requested covers an approved `courses:read`; `*` covers anything).
     *
     * @param  list<string>  $requested
     * @param  list<string>  $approved
     * @return list<string>
     */
    private function intersect(array $requested, array $approved): array
    {
        $out = array_values(array_filter($approved, fn (string $s) => TokenScopes::covers($requested, [$s])));

        return $out === [] ? [] : TokenScopes::normalize($out);
    }

    private function hash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }

    private function newUserCode(): string
    {
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }
}
