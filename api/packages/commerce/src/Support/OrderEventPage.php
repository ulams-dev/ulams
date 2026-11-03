<?php

namespace Ulams\Commerce\Support;

/** A page of the provider's event feed; `nextCursor` is where the next read continues. */
final class OrderEventPage
{
    /** @param OrderEventEnvelope[] $events */
    public function __construct(public readonly array $events, public readonly ?string $nextCursor)
    {
    }
}
