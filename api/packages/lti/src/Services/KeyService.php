<?php

namespace Ulams\Lti\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Ulams\Lti\Models\LtiKey;

/**
 * The tenant's LTI signing keys. Three states: `next` (published, not used yet), `active` (signs
 * everything) and `retired` (still published for a grace period). Rotation promotes next to
 * active, so platforms and tools that cache our JWKS already know the new key.
 */
class KeyService
{
    public function ensureKeys(): void
    {
        DB::transaction(function () {
            if (!LtiKey::query()->where('status', LtiKey::ACTIVE)->exists()) {
                $this->generate(LtiKey::ACTIVE);
            }
            if (!LtiKey::query()->where('status', LtiKey::NEXT)->exists()) {
                $this->generate(LtiKey::NEXT);
            }
        });
    }

    /**
     * @return array{activated: string, retired: ?string, next: string, deleted: int}
     */
    public function rotate(): array
    {
        return DB::transaction(function () {
            $this->ensureKeys();

            /** @var LtiKey $active */
            $active = LtiKey::query()->where('status', LtiKey::ACTIVE)->lockForUpdate()->firstOrFail();
            /** @var LtiKey $next */
            $next = LtiKey::query()->where('status', LtiKey::NEXT)->lockForUpdate()->firstOrFail();

            $active->update(['status' => LtiKey::RETIRED, 'retired_at' => Carbon::now()]);
            $next->update(['status' => LtiKey::ACTIVE, 'activated_at' => Carbon::now()]);
            $new = $this->generate(LtiKey::NEXT);

            $deleted = LtiKey::query()
                ->where('status', LtiKey::RETIRED)
                ->where('retired_at', '<', Carbon::now()->subDays((int) config('ulams_lti.retired_key_grace_days', 30)))
                ->delete();

            return ['activated' => $next->kid, 'retired' => $active->kid, 'next' => $new->kid, 'deleted' => $deleted];
        });
    }

    public function activeKey(): LtiKey
    {
        $key = LtiKey::query()->where('status', LtiKey::ACTIVE)->first();
        if ($key === null) {
            $this->ensureKeys();
            $key = LtiKey::query()->where('status', LtiKey::ACTIVE)->firstOrFail();
        }

        return $key;
    }

    /**
     * Public JWKS: next, active and retired keys within the grace period.
     */
    public function jwks(): array
    {
        $keys = LtiKey::query()
            ->whereIn('status', [LtiKey::NEXT, LtiKey::ACTIVE])
            ->orWhere(fn ($q) => $q->where('status', LtiKey::RETIRED)
                ->where('retired_at', '>=', Carbon::now()->subDays((int) config('ulams_lti.retired_key_grace_days', 30))))
            ->orderBy('id')
            ->get();

        return ['keys' => $keys->map(fn (LtiKey $key) => $key->public_jwk)->values()->all()];
    }

    public function sign(array $claims): string
    {
        $key = $this->activeKey();

        return JWT::encode($claims, $key->private_key, 'RS256', $key->kid);
    }

    /**
     * Verification keys of our own published key set, by kid.
     *
     * @return array<string, Key>
     */
    public function verificationKeys(): array
    {
        $keys = [];
        foreach (LtiKey::query()->whereIn('status', [LtiKey::NEXT, LtiKey::ACTIVE, LtiKey::RETIRED])->get() as $key) {
            $details = openssl_pkey_get_details(openssl_pkey_get_private($key->private_key));
            $keys[$key->kid] = new Key($details['key'], 'RS256');
        }

        return $keys;
    }

    private function generate(string $status): LtiKey
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => (int) config('ulams_lti.key_bits', 2048),
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if ($resource === false || !openssl_pkey_export($resource, $pem)) {
            throw new RuntimeException('Could not generate an RSA key: ' . openssl_error_string());
        }
        $details = openssl_pkey_get_details($resource);
        $kid = bin2hex(random_bytes(16));

        return LtiKey::query()->create([
            'kid' => $kid,
            'private_key' => $pem,
            'public_jwk' => [
                'kty' => 'RSA',
                'alg' => 'RS256',
                'use' => 'sig',
                'kid' => $kid,
                'n' => self::base64url($details['rsa']['n']),
                'e' => self::base64url($details['rsa']['e']),
            ],
            'status' => $status,
            'activated_at' => $status === LtiKey::ACTIVE ? Carbon::now() : null,
        ]);
    }

    private static function base64url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
