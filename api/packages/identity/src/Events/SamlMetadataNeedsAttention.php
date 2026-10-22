<?php

namespace Ulams\Identity\Events;

/**
 * The daily metadata refresh found a change it will not apply on its own (a different entity id, certificates
 * with nothing in common with the trusted ones): an administrator has to look. Listeners can notify people; the
 * change is also in the identity audit trail and in the provider's `last_error`.
 */
final class SamlMetadataNeedsAttention
{
    public function __construct(public readonly string $providerId, public readonly string $slug, public readonly string $reason)
    {
    }
}
