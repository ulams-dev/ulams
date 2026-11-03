<?php

namespace Ulams\Tenancy\Tests\Mocks;

use Ulams\Tenancy\Services\Contracts\StorageIdentityProvisionerContract;
use Ulams\Tenancy\Storage\StorageCredentials;

/**
 * An object store's identity API in memory: one identity per slug, any number of keys.
 */
class FakeStorageIdentityProvisioner implements StorageIdentityProvisionerContract
{
    /** @var array<string, array{bucket: string, keys: array<string, string>}> slug => identity */
    public array $identities = [];

    /** @var list<string> */
    public array $log = [];

    private int $counter = 0;

    public function ensure(string $slug, string $bucket, ?StorageCredentials $current = null): ?StorageCredentials
    {
        $this->log[] = "ensure:{$slug}";
        $this->identities[$slug]['bucket'] = $bucket;
        $this->identities[$slug]['keys'] ??= [];
        if ($current !== null && isset($this->identities[$slug]['keys'][$current->accessKey])) {
            return $current;
        }
        $issued = $this->issue($slug);
        $this->identities[$slug]['keys'] = [$issued->accessKey => $issued->secret()];

        return $issued;
    }

    public function rotate(string $slug, string $bucket): ?StorageCredentials
    {
        $this->log[] = "rotate:{$slug}";
        $this->identities[$slug]['bucket'] = $bucket;

        return $this->issue($slug);
    }

    public function prune(string $slug, StorageCredentials $keep): void
    {
        $this->log[] = "prune:{$slug}";
        $this->identities[$slug]['keys'] = array_intersect_key($this->identities[$slug]['keys'] ?? [], [$keep->accessKey => true]);
    }

    public function revoke(string $slug): void
    {
        $this->log[] = "revoke:{$slug}";
        unset($this->identities[$slug]);
    }

    private function issue(string $slug): StorageCredentials
    {
        $this->counter++;
        $credentials = new StorageCredentials("AKIA{$slug}{$this->counter}", "secret-{$slug}-{$this->counter}-" . str_repeat('x', 20));
        $this->identities[$slug]['keys'][$credentials->accessKey] = $credentials->secret();

        return $credentials;
    }
}
