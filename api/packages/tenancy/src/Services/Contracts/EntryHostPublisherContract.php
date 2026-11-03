<?php

namespace Ulams\Tenancy\Services\Contracts;

use Ulams\Tenancy\Models\Tenant;

/** Brings an academy's own copy of its active entry hosts up to date with the platform table. */
interface EntryHostPublisherContract
{
    /**
     * @throws \RuntimeException when the academy could not be reached; the copy is then out of date until the next try
     */
    public function publish(Tenant $tenant): void;
}
