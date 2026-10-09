<?php

namespace Ulams\Auth\Support;

use Laravel\Passport\Token;
use Ulams\Auth\Models\ApiTokenMeta;

/** A freshly minted scoped token: the secret exists only in this object and in the one response. */
final class IssuedToken
{
    public function __construct(
        public readonly string $secret,
        public readonly Token $token,
        public readonly ApiTokenMeta $meta,
    ) {
    }
}
