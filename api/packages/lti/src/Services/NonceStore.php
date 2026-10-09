<?php

namespace Ulams\Lti\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Single-use values with an expiry, stored in the tenant database (`lti_nonces`): OIDC state and
 * nonce (tool side), login hints and JWT ids (platform side), one-time codes. A value can be
 * used once; a replay is refused.
 */
class NonceStore
{
    public const STATE = 'state';
    public const NONCE = 'nonce';
    public const LOGIN_HINT = 'login_hint';
    public const JTI = 'jti';
    public const CODE = 'code';
    public const LAUNCH = 'launch';
    /** tool side: a login that put its nonce in the platform's storage (client side postMessage) */
    public const STORAGE = 'storage';

    /**
     * Records a value. Returns false when it was already recorded and has not expired (replay).
     */
    public function remember(string $type, string $value, int $ttl, ?array $payload = null): bool
    {
        $this->prune($type, $value);

        // ON CONFLICT DO NOTHING: a replay must not abort a surrounding transaction (Postgres)
        $inserted = DB::table('lti_nonces')->insertOrIgnore([
            'type' => $type,
            'value' => $this->key($value),
            'payload' => $payload === null ? null : encrypt(json_encode($payload)),
            'expires_at' => Carbon::now()->addSeconds($ttl),
            'created_at' => Carbon::now(),
        ]);

        return $inserted === 1;
    }

    /**
     * Takes a value out of the store. Returns its payload ([] when it had none), or null when it
     * does not exist, has expired or was taken already.
     */
    public function take(string $type, string $value): ?array
    {
        $row = DB::table('lti_nonces')
            ->where('type', $type)
            ->where('value', $this->key($value))
            ->first();
        if ($row === null) {
            return null;
        }

        $deleted = DB::table('lti_nonces')->where('id', $row->id)->delete();
        if ($deleted !== 1 || Carbon::parse($row->expires_at)->isPast()) {
            return null;
        }

        return $row->payload === null ? [] : (array) json_decode(decrypt($row->payload), true);
    }

    /**
     * Reads a value without taking it (null when missing or expired).
     */
    public function peek(string $type, string $value): ?array
    {
        $row = DB::table('lti_nonces')
            ->where('type', $type)
            ->where('value', $this->key($value))
            ->where('expires_at', '>', Carbon::now())
            ->first();

        return $row === null ? null : ($row->payload === null ? [] : (array) json_decode(decrypt($row->payload), true));
    }

    public function pruneExpired(): int
    {
        return DB::table('lti_nonces')->where('expires_at', '<', Carbon::now())->delete();
    }

    /** Values may be long (JWT ids, states); store a fixed-length hash. */
    private function key(string $value): string
    {
        return hash('sha256', $value);
    }

    private function prune(string $type, string $value): void
    {
        DB::table('lti_nonces')
            ->where('type', $type)
            ->where('value', $this->key($value))
            ->where('expires_at', '<', Carbon::now())
            ->delete();
    }
}
