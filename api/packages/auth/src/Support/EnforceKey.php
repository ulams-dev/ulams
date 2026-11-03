<?php

namespace Ulams\Auth\Support;

/** The rate limiter key of a scoped token's per-minute limit (shared by the middleware and the usage summary). */
final class EnforceKey
{
    public static function for(string $tokenId): string
    {
        return 'ulams-token:' . $tokenId;
    }
}
