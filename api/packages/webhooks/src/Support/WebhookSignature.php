<?php

namespace Ulams\Webhooks\Support;

/**
 * Stripe-style signature: `Ulams-Signature: t=<unix seconds>,v1=<hex hmac>` where the HMAC-SHA256 is
 * taken over `<t>.<raw body>` with the endpoint secret. The timestamp is part of the signed text, so a
 * captured request cannot be replayed with a fresh timestamp, and receivers reject old timestamps.
 */
final class WebhookSignature
{
    public const HEADER = 'Ulams-Signature';

    public static function sign(string $secret, string $body, ?int $timestamp = null): string
    {
        $t = $timestamp ?? time();

        return 't=' . $t . ',v1=' . self::mac($secret, $t, $body);
    }

    /**
     * Verify a header against the raw request body. This is the reference implementation the docs
     * show receivers; it compares in constant time and accepts any `v1` value of the header.
     */
    public static function verify(string $header, string $body, string $secret, int $tolerance = 300, ?int $now = null): bool
    {
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($key === 't' && ctype_digit($value)) {
                $timestamp = (int) $value;
            } elseif ($key === 'v1' && $value !== '') {
                $signatures[] = $value;
            }
        }
        if ($timestamp === null || $signatures === []) {
            return false;
        }
        if ($tolerance > 0 && abs(($now ?? time()) - $timestamp) > $tolerance) {
            return false;
        }
        $expected = self::mac($secret, $timestamp, $body);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    public static function newSecret(): string
    {
        return 'whsec_' . bin2hex(random_bytes(24));
    }

    private static function mac(string $secret, int $timestamp, string $body): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    }
}
