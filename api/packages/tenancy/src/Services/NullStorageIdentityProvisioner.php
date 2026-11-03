<?php

namespace Ulams\Tenancy\Services;

use Ulams\Tenancy\Services\Contracts\StorageIdentityProvisionerContract;
use Ulams\Tenancy\Storage\StorageCredentials;

/**
 * No identities: every tenant uses the platform's S3 keys. For AWS S3, Cloudflare R2 and other external
 * object stores where the operator manages IAM (TENANCY_S3_IDENTITY_DRIVER=none, the default).
 */
class NullStorageIdentityProvisioner implements StorageIdentityProvisionerContract
{
    public function ensure(string $slug, string $bucket, ?StorageCredentials $current = null): ?StorageCredentials
    {
        return null;
    }

    public function rotate(string $slug, string $bucket): ?StorageCredentials
    {
        return null;
    }

    public function prune(string $slug, StorageCredentials $keep): void
    {
    }

    public function revoke(string $slug): void
    {
    }
}
