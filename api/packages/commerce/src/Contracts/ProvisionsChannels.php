<?php

namespace Ulams\Commerce\Contracts;

use Ulams\Commerce\Support\ChannelRef;
use Ulams\Commerce\Support\ChannelSpec;

/**
 * Optional capability: the provider has one sales channel per tenant (Sylius). `ensureChannel()` is idempotent: it creates
 * what is missing, repairs what drifted, never changes what cannot change (the base currency) and fails loudly instead.
 */
interface ProvisionsChannels
{
    public function ensureChannel(ChannelSpec $spec): ChannelRef;

    /** Takes the channel off sale; orders and products stay (accounting retention). */
    public function disableChannel(string $code): void;
}
