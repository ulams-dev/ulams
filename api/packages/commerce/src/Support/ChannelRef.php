<?php

namespace Ulams\Commerce\Support;

/** A tenant's sales channel at a provider. */
final class ChannelRef
{
    public function __construct(public readonly string $provider, public readonly string $code, public readonly bool $created)
    {
    }
}
