<?php

namespace Ulams\Tenancy\Services;

use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Services\Contracts\EntryHostPublisherContract;

/** Publishes nothing (tests of the platform table that do not look at the academies). */
class NullEntryHostPublisher implements EntryHostPublisherContract
{
    public function publish(Tenant $tenant): void
    {
    }
}
