<?php

namespace Ulams\Tenancy\Tests\Feature;

use Ulams\Tenancy\Models\EntryPoint;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Services\EntryPointService;
use Ulams\Tenancy\Support\TenantNaming;
use Ulams\Tenancy\Tests\TestCase;

/**
 * The public lookup the learner front makes for a host (ADR 0195): what it returns, what it never returns, and
 * that a host only ever resolves to its own academy.
 */
class EntryPointResolveTest extends TestCase
{
    private EntryPointService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ulams_tenancy.entry_trusted_suffixes' => ['.example.test'], 'ulams_tenancy.tenant_slug' => null]);
        $this->service = app(EntryPointService::class);
        foreach (['coffee', 'tea'] as $slug) {
            Tenant::query()->create(array_merge(TenantNaming::newTenantAttributes($slug), ['status' => Tenant::STATUS_ACTIVE]));
        }
    }

    /** @return array<string, mixed> */
    private function entry(string $slug, array $extra = []): array
    {
        return $this->service->create($slug, array_merge(['host' => "sales.{$slug}.example.test", 'key' => 'sales', 'kind' => 'department', 'target' => ['group_id' => 7], 'label' => 'Sales', 'theme' => ['accent' => '#c2552d']], $extra), null);
    }

    public function testAnActiveHostResolvesToItsAcademyWithTheFieldsTheFrontNeeds(): void
    {
        $this->entry('coffee', ['locale' => 'pl']);

        $response = $this->getJson('/api/platform/entry-points/resolve?host=sales.coffee.example.test')->assertOk();

        $this->assertSame([
            'tenant' => 'coffee',
            'host' => 'sales.coffee.example.test',
            'kind' => 'department',
            'key' => 'sales',
            'label' => 'Sales',
            'target' => ['group_id' => 7],
            'theme' => ['accent' => '#c2552d'],
            'seo' => ['canonical' => 'tenant', 'index' => false],
            'locale' => 'pl',
        ], $response->json('data'));
    }

    public function testItNeedsNoLoginAndNoSwitch(): void
    {
        $this->entry('coffee');
        config(['ulams_tenancy.platform_api' => false]);

        $this->getJson('/api/platform/entry-points/resolve?host=sales.coffee.example.test')->assertOk();
    }

    public function testTheAnswerHasNoStatusIdTokenOrPersonalData(): void
    {
        $entry = $this->entry('coffee');
        $this->service->update('coffee', $entry['id'], [], ['id' => 1, 'email' => 'admin@coffee.test']);
        $body = $this->getJson('/api/platform/entry-points/resolve?host=sales.coffee.example.test')->assertOk()->getContent();

        foreach (['verification', 'status', 'tenant_id', 'admin@coffee.test', 'actor', 'created_at', '"id"', $entry['id'], 'dns'] as $secret) {
            $this->assertStringNotContainsString($secret, $body);
        }
    }

    public function testPendingDisabledAndUnknownHostsAreOneAnd404(): void
    {
        config(['ulams_tenancy.entry_trusted_suffixes' => []]);
        $pending = $this->entry('coffee', ['host' => 'pending.coffee.example.org', 'key' => 'p']);
        $this->assertSame('pending_dns', $pending['status']);
        config(['ulams_tenancy.entry_trusted_suffixes' => ['.example.test']]);
        $disabled = $this->entry('coffee', ['host' => 'off.coffee.example.test', 'key' => 'off']);
        $this->service->update('coffee', $disabled['id'], ['status' => 'disabled'], null);

        $bodies = [];
        foreach (['pending.coffee.example.org', 'off.coffee.example.test', 'nope.coffee.example.test', '10.0.0.1', 'bad host', str_repeat('a', 300)] as $host) {
            $response = $this->getJson('/api/platform/entry-points/resolve?host=' . rawurlencode($host))->assertNotFound();
            $bodies[] = $response->getContent();
        }
        $this->assertCount(1, array_unique($bodies), 'every refusal looks the same');
    }

    public function testHostsAreCaseAndTrailingDotInsensitive(): void
    {
        $this->entry('coffee');

        $this->getJson('/api/platform/entry-points/resolve?host=Sales.Coffee.Example.Test.')->assertOk()->assertJsonPath('data.tenant', 'coffee');
    }

    public function testAHostResolvesOnlyToItsOwnAcademy(): void
    {
        $this->entry('coffee');
        $this->entry('tea');

        $this->getJson('/api/platform/entry-points/resolve?host=sales.coffee.example.test')->assertJsonPath('data.tenant', 'coffee');
        $this->getJson('/api/platform/entry-points/resolve?host=sales.tea.example.test')->assertJsonPath('data.tenant', 'tea');
        // an academy's own hosts are not entry points
        $this->getJson('/api/platform/entry-points/resolve?host=coffee.app.localhost')->assertNotFound();
        $this->getJson('/api/platform/entry-points/resolve?host=coffee.localhost')->assertNotFound();
    }

    public function testTheKeyFallbackNeedsTheAcademyAndNeverCrossesAcademies(): void
    {
        $this->entry('coffee');
        $this->entry('tea', ['host' => 'other.tea.example.test', 'key' => 'other']);

        $this->getJson('/api/platform/entry-points/resolve?tenant=coffee&key=sales')->assertOk()->assertJsonPath('data.host', 'sales.coffee.example.test');
        // tea has no "sales" under coffee's name, and coffee has no "other"
        $this->getJson('/api/platform/entry-points/resolve?tenant=coffee&key=other')->assertNotFound();
        $this->getJson('/api/platform/entry-points/resolve?tenant=tea&key=sales')->assertNotFound();
        $this->getJson('/api/platform/entry-points/resolve?tenant=nobody&key=sales')->assertNotFound();
    }

    public function testAnswersNeedExactlyOneKindOfQuestion(): void
    {
        $this->entry('coffee');

        foreach (['', '?host=', '?tenant=coffee', '?key=sales', '?host=sales.coffee.example.test&tenant=coffee&key=sales', '?host[]=sales.coffee.example.test', '?tenant[]=coffee&key=sales'] as $query) {
            $this->getJson('/api/platform/entry-points/resolve' . $query)->assertNotFound();
        }
    }

    public function testAnEntryPointOfAnAcademyThatIsNotActiveDoesNotResolve(): void
    {
        $this->entry('coffee');
        Tenant::query()->where('slug', 'coffee')->update(['status' => Tenant::STATUS_FAILED]);

        $this->getJson('/api/platform/entry-points/resolve?host=sales.coffee.example.test')->assertNotFound();
        $this->getJson('/api/platform/entry-points/resolve?tenant=coffee&key=sales')->assertNotFound();
    }

    public function testDeletingOrDisablingStopsItAtOnce(): void
    {
        $entry = $this->entry('coffee');
        $this->getJson('/api/platform/entry-points/resolve?host=sales.coffee.example.test')->assertOk();

        $this->service->update('coffee', $entry['id'], ['status' => 'disabled'], null);
        $this->getJson('/api/platform/entry-points/resolve?host=sales.coffee.example.test')->assertNotFound();

        $this->service->update('coffee', $entry['id'], ['status' => 'active'], null);
        $this->getJson('/api/platform/entry-points/resolve?host=sales.coffee.example.test')->assertOk();
        $this->service->delete('coffee', $entry['id'], null);
        $this->getJson('/api/platform/entry-points/resolve?host=sales.coffee.example.test')->assertNotFound();
    }

    public function testItIsA404OnATenantHost(): void
    {
        $this->entry('coffee');
        config(['ulams_tenancy.tenant_slug' => 'coffee']);

        $this->getJson('/api/platform/entry-points/resolve?host=sales.coffee.example.test')->assertNotFound();
        $this->getJson('/api/platform/entry-points/resolve?tenant=coffee&key=sales')->assertNotFound();
    }

    public function testItIsThrottledPerAddress(): void
    {
        config(['ulams_tenancy.entry_resolve_rate' => 3]);

        foreach (range(1, 3) as $_) {
            $this->getJson('/api/platform/entry-points/resolve?host=x.coffee.example.test')->assertNotFound();
        }
        $this->getJson('/api/platform/entry-points/resolve?host=x.coffee.example.test')->assertStatus(429);
    }

    public function testTheReservedKeyCannotBeTaken(): void
    {
        try {
            $this->entry('coffee', ['key' => 'main']);
            $this->fail('Accepted the reserved key.');
        } catch (\Ulams\Tenancy\Exceptions\EntryPointException $e) {
            $this->assertArrayHasKey('key', $e->errors);
        }
        $this->assertSame(0, EntryPoint::query()->count());
    }
}
