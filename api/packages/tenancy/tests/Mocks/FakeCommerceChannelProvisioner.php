<?php

namespace Ulams\Tenancy\Tests\Mocks;

use RuntimeException;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Services\Contracts\CommerceChannelProvisionerContract;

/** An in-memory commerce provider with channels. */
class FakeCommerceChannelProvisioner implements CommerceChannelProvisionerContract
{
    public bool $on = true;
    public ?string $fail = null;
    public bool $disableFails = false;
    /** @var array<string,array{url:string,secret:string,calls:int}> */
    public array $channels = [];
    /** @var list<string> */
    public array $disabled = [];

    public function enabled(): bool
    {
        return $this->on;
    }

    public function ensure(Tenant $tenant, string $webhookUrl, string $webhookSecret): string
    {
        if ($this->fail !== null) {
            throw new RuntimeException($this->fail);
        }
        $calls = ($this->channels[$tenant->slug]['calls'] ?? 0) + 1;
        $this->channels[$tenant->slug] = ['url' => $webhookUrl, 'secret' => $webhookSecret, 'calls' => $calls];

        return $tenant->slug;
    }

    public function disable(Tenant $tenant): void
    {
        if ($this->disableFails) {
            throw new RuntimeException('Sylius is down');
        }
        $this->disabled[] = $tenant->slug;
    }
}
