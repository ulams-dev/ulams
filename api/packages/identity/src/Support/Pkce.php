<?php

namespace Ulams\Identity\Support;

/** PKCE (RFC 7636) with the S256 method. */
final class Pkce
{
    /** 43 characters of base64url: 256 bits. */
    public static function verifier(): string
    {
        return self::base64Url(random_bytes(32));
    }

    public static function challenge(string $verifier): string
    {
        return self::base64Url(hash('sha256', $verifier, true));
    }

    public static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
