<?php

namespace Ulams\Tenancy\Services\Contracts;

interface BucketProvisionerContract
{
    /**
     * Creates the bucket when missing and applies an anonymous read-only policy.
     */
    public function ensure(string $bucket): void;

    /**
     * Deletes every object and the bucket itself. A missing bucket is ignored.
     */
    public function delete(string $bucket): void;
}
