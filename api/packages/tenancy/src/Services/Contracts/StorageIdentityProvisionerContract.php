<?php

namespace Ulams\Tenancy\Services\Contracts;

use Ulams\Tenancy\Storage\StorageCredentials;

/**
 * Gives one tenant its own object-store identity, allowed to use that tenant's bucket and nothing else
 * (ADR 0041). Backends differ in how identities are made, so the tenancy code only knows this contract.
 *
 * A provisioner that manages no identities (external S3 where the operator manages IAM) returns null
 * everywhere, and the tenant keeps using the platform keys.
 */
interface StorageIdentityProvisionerContract
{
    /**
     * Makes sure the tenant has an identity limited to its bucket and returns the key to use. Keeps
     * `$current` when the store still knows it, so running a step twice never changes a working key;
     * issues a new key when it is unknown to the store or missing (a lost record).
     */
    public function ensure(string $slug, string $bucket, ?StorageCredentials $current = null): ?StorageCredentials;

    /**
     * Issues an additional key for the tenant's identity. The old keys keep working until `prune()`,
     * so the new one can be stored and rolled out first.
     */
    public function rotate(string $slug, string $bucket): ?StorageCredentials;

    /**
     * Deletes every key of the tenant's identity except `$keep`.
     */
    public function prune(string $slug, StorageCredentials $keep): void;

    /**
     * Deletes the tenant's identity and all its keys. A missing identity is ignored.
     */
    public function revoke(string $slug): void;
}
