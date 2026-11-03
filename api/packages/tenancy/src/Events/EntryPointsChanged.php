<?php

namespace Ulams\Tenancy\Events;

use Ulams\Tenancy\Models\Tenant;

/** An entry point of the tenant was added, changed, disabled or deleted (platform process). */
class EntryPointsChanged
{
    public function __construct(public readonly Tenant $tenant)
    {
    }
}
