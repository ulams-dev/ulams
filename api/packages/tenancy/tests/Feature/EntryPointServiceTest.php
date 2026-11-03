<?php

namespace Ulams\Tenancy\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Ulams\Tenancy\Events\EntryPointsChanged;
use Ulams\Tenancy\Exceptions\EntryPointException;
use Ulams\Tenancy\Models\EntryPoint;
use Ulams\Tenancy\Models\EntryPointAudit;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Services\EntryPointService;
use Ulams\Tenancy\Support\TenantNaming;
use Ulams\Tenancy\Tests\TestCase;

/**
 * The platform side of entry points (ADR 0195): validation, host rules, statuses, audit and the separation
 * between tenants. Hosts are example.test or localhost names (no real hosts in tests).
 */
class EntryPointServiceTest extends TestCase
{
    private EntryPointService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'ulams_tenancy.entry_platform_suffixes' => ['.app.localhost'],
            'ulams_tenancy.entry_trusted_suffixes' => [],
            'ulams_tenancy.max_entry_points' => 20,
        ]);
        $this->service = app(EntryPointService::class);
    }

    private function tenant(string $slug = 'coffee'): Tenant
    {
        return Tenant::query()->create(array_merge(TenantNaming::newTenantAttributes($slug), ['status' => Tenant::STATUS_ACTIVE]));
    }

    /** @param array<string, mixed> $extra */
    private function input(array $extra = []): array
    {
        return array_merge([
            'host' => 'sales.coffee.example.test',
            'key' => 'sales',
            'kind' => 'department',
            'target' => ['group_id' => 7],
            'label' => 'Sales academy',
        ], $extra);
    }

    private function refused(callable $call, int $status, ?string $field = null): EntryPointException
    {
        try {
            $call();
        } catch (EntryPointException $e) {
            $this->assertSame($status, $e->status);
            if ($field !== null) {
                $this->assertArrayHasKey($field, $e->errors, json_encode($e->errors));
            }

            return $e;
        }
        $this->fail('The service accepted the input.');
    }

    public function testACustomDomainStartsPendingWithDnsInstructions(): void
    {
        $this->tenant();

        $created = $this->service->create('coffee', $this->input(), ['id' => 5, 'email' => 'admin@coffee.test']);

        $this->assertSame('pending_dns', $created['status']);
        $this->assertSame('sales.coffee.example.test', $created['host']);
        $this->assertSame(['group_id' => 7], $created['target']);
        $this->assertSame('_ulams-verify.sales.coffee.example.test', $created['dns']['txt_name']);
        $this->assertMatchesRegularExpression('/^ulams-verify=[0-9a-f]{32}$/', $created['dns']['txt_value']);
        $this->assertSame('http://coffee.app.localhost/go/sales', $created['go_url']);
        $this->assertNull($created['verified_at']);
        $this->assertSame(['canonical' => 'tenant', 'index' => false], $created['seo'], 'a department host is noindex by default');
    }

    public function testAPlatformHostIsActiveAtOnceAndMustBeFlat(): void
    {
        $this->tenant();

        $created = $this->service->create('coffee', $this->input(['host' => 'coffee-sales.app.localhost']), null);
        $this->assertSame('active', $created['status']);
        $this->assertNull($created['dns']);
        $this->assertNotNull($created['verified_at']);

        foreach (['sales.coffee.app.localhost', 'bravo-sales.app.localhost', 'coffee-.app.localhost'] as $i => $host) {
            $this->refused(fn () => $this->service->create('coffee', $this->input(['host' => $host, 'key' => "k{$i}"]), null), 422, 'host');
        }
    }

    public function testATrustedDevelopmentSuffixIsActiveAtOnce(): void
    {
        $this->tenant();
        config(['ulams_tenancy.entry_trusted_suffixes' => ['.example.test']]);

        $this->assertSame('active', $this->service->create('coffee', $this->input(), null)['status']);
    }

    public function testSeoDefaultsFollowTheKindAndCanBeOverridden(): void
    {
        $this->tenant();
        $course = $this->service->create('coffee', $this->input(['host' => 'learn.coffee.example.test', 'key' => 'learn', 'kind' => 'course', 'target' => ['course_id' => 3]]), null);
        $academy = $this->service->create('coffee', $this->input(['host' => 'b.coffee.example.test', 'key' => 'b', 'kind' => 'academy', 'target' => null, 'seo' => ['index' => false, 'title' => 'Brand B']]), null);

        $this->assertSame(['canonical' => 'tenant', 'index' => true], $course['seo']);
        $this->assertSame(['canonical' => 'tenant', 'index' => false, 'title' => 'Brand B'], $academy['seo']);
    }

    public function testCanonicalSelfIsForCourseEntryPointsOnly(): void
    {
        $this->tenant();
        $this->refused(fn () => $this->service->create('coffee', $this->input(['seo' => ['canonical' => 'self']]), null), 422, 'seo.canonical');

        $ok = $this->service->create('coffee', $this->input(['host' => 'learn.coffee.example.test', 'key' => 'learn', 'kind' => 'course', 'target' => ['course_id' => 3], 'seo' => ['canonical' => 'self']]), null);
        $this->assertSame('self', $ok['seo']['canonical']);
    }

    public function testTargetShapeFollowsTheKind(): void
    {
        $this->tenant();
        $cases = [
            ['kind' => 'department', 'target' => null],
            ['kind' => 'department', 'target' => ['course_id' => 1]],
            ['kind' => 'department', 'target' => ['group_id' => 0]],
            ['kind' => 'course', 'target' => ['group_id' => 1]],
            ['kind' => 'role', 'target' => ['role' => 'Not A Key']],
            ['kind' => 'academy', 'target' => ['group_id' => 1]],
        ];
        foreach ($cases as $i => $case) {
            $this->refused(fn () => $this->service->create('coffee', $this->input($case + ['host' => "h{$i}.coffee.example.test", 'key' => "k{$i}"]), null), 422);
        }

        $this->assertSame(['role' => 'manager'], $this->service->create('coffee', $this->input(['kind' => 'role', 'target' => ['role' => 'manager']]), null)['target']);
        $this->assertSame([], $this->service->create('coffee', $this->input(['host' => 'x.coffee.example.test', 'key' => 'x', 'kind' => 'academy', 'target' => null]), null)['target']);
        $this->assertSame(0, EntryPoint::query()->where('key', 'like', 'k%')->count());
    }

    public function testHostRules(): void
    {
        $this->tenant();
        $this->tenant('tea');
        config(['ulams_tenancy.platform_hosts' => ['api.localhost', 'platform.example.test']]);

        $refused = [
            '127.0.0.1', '10.0.0.1', '[::1]', 'localhost', 'sales', 'Sales.Example.Test:8080', 'sales.example.test/path', 'user@sales.example.test',
            'sales_x.example.test', '-bad.example.test', 'sales.example.123', str_repeat('a', 64) . '.example.test',
            'coffee.content.example.test', 'api.localhost', 'platform.example.test',
            // hosts of academies
            'coffee.app.localhost', 'coffee.admin.localhost', 'coffee.localhost', 'tea.localhost',
        ];
        foreach ($refused as $i => $host) {
            $this->refused(fn () => $this->service->create('coffee', $this->input(['host' => $host, 'key' => "k{$i}"]), null), 422, 'host');
        }
        $this->assertSame(0, EntryPoint::query()->count());

        // case and a trailing dot do not create a second spelling
        $created = $this->service->create('coffee', $this->input(['host' => 'Sales.Coffee.Example.Test.']), null);
        $this->assertSame('sales.coffee.example.test', $created['host']);
    }

    public function testAHostBelongsToOneEntryPointOnThePlatform(): void
    {
        $this->tenant();
        $this->tenant('tea');
        $this->service->create('coffee', $this->input(), null);

        $this->refused(fn () => $this->service->create('coffee', $this->input(['key' => 'other']), null), 422, 'host');
        // another tenant cannot take it either
        $this->refused(fn () => $this->service->create('tea', $this->input(['key' => 'tea1']), null), 422, 'host');
        $this->assertSame(1, EntryPoint::query()->count());
    }

    public function testAKeyIsUniquePerTenantOnly(): void
    {
        $this->tenant();
        $this->tenant('tea');
        $this->service->create('coffee', $this->input(), null);

        $this->refused(fn () => $this->service->create('coffee', $this->input(['host' => 'b.coffee.example.test']), null), 422, 'key');
        $this->service->create('tea', $this->input(['host' => 'sales.tea.example.test']), null);
        $this->assertSame(2, EntryPoint::query()->where('key', 'sales')->count());
    }

    public function testTheNumberOfEntryPointsIsLimitedPerTenant(): void
    {
        $this->tenant();
        $this->tenant('tea');
        config(['ulams_tenancy.max_entry_points' => 2]);
        $this->service->create('coffee', $this->input(['host' => 'a.coffee.example.test', 'key' => 'a']), null);
        $this->service->create('coffee', $this->input(['host' => 'b.coffee.example.test', 'key' => 'b']), null);

        $this->refused(fn () => $this->service->create('coffee', $this->input(['host' => 'c.coffee.example.test', 'key' => 'c']), null), 422, 'host');
        // the limit is per tenant
        $this->service->create('tea', $this->input(['host' => 'a.tea.example.test', 'key' => 'a']), null);
        $this->assertSame(3, EntryPoint::query()->count());
    }

    public function testThemeIsCheckedAndCleaned(): void
    {
        $this->tenant();
        foreach ([['accent' => 'red'], ['accent' => '#12'], ['preset' => 'no-such-preset'], ['logo_alt' => str_repeat('x', 121)]] as $i => $theme) {
            $this->refused(fn () => $this->service->create('coffee', $this->input(['host' => "t{$i}.coffee.example.test", 'key' => "t{$i}", 'theme' => $theme]), null), 422);
        }

        $created = $this->service->create('coffee', $this->input(['theme' => ['accent' => '#C2552D', 'logo_alt' => 'Sales', 'junk' => 'dropped']]), null);
        $this->assertSame(['accent' => '#c2552d', 'logo_alt' => 'Sales'], $created['theme']);
    }

    public function testUpdateChangesOnlyWhatIsAllowedAndAuditsIt(): void
    {
        $this->tenant();
        $created = $this->service->create('coffee', $this->input(), ['id' => 1, 'email' => 'a@coffee.test']);

        // host, key and kind are fixed: the validator does not even carry them
        $updated = $this->service->update('coffee', $created['id'], ['host' => 'evil.example.test', 'key' => 'other', 'kind' => 'course', 'label' => 'Sales 2'], ['id' => 2, 'email' => 'b@coffee.test']);
        $this->assertSame(['sales.coffee.example.test', 'sales', 'department', 'Sales 2'], [$updated['host'], $updated['key'], $updated['kind'], $updated['label']]);

        $this->refused(fn () => $this->service->update('coffee', $created['id'], ['target' => ['course_id' => 1]], null), 422);
        $this->assertSame(['group_id' => 9], $this->service->update('coffee', $created['id'], ['target' => ['group_id' => '9']], null)['target']);

        $audit = $this->service->audit('coffee');
        $this->assertSame(['updated', 'updated', 'created'], array_column($audit, 'action'));
        $this->assertSame('b@coffee.test', $audit[1]['actor_email']);
        $this->assertSame(['from' => 'Sales academy', 'to' => 'Sales 2'], $audit[1]['changes']['label']);
    }

    public function testAnUnchangedUpdateWritesNoAuditAndFiresNoEvent(): void
    {
        $tenant = $this->tenant();
        $created = $this->service->create('coffee', $this->input(), null);
        Event::fake([EntryPointsChanged::class]);

        $this->service->update('coffee', $created['id'], ['label' => 'Sales academy'], null);

        Event::assertNotDispatched(EntryPointsChanged::class);
        $this->assertSame(1, EntryPointAudit::query()->where('tenant_id', $tenant->id)->count());
    }

    public function testDisablingAndEnablingNeedsAVerifiedHost(): void
    {
        $this->tenant();
        $pending = $this->service->create('coffee', $this->input(), null);
        $this->refused(fn () => $this->service->update('coffee', $pending['id'], ['status' => 'active'], null), 422, 'status');
        $this->assertSame('disabled', $this->service->update('coffee', $pending['id'], ['status' => 'disabled'], null)['status']);

        $active = $this->service->create('coffee', $this->input(['host' => 'coffee-b.app.localhost', 'key' => 'b']), null);
        $this->assertSame('disabled', $this->service->update('coffee', $active['id'], ['status' => 'disabled'], null)['status']);
        $this->assertSame('active', $this->service->update('coffee', $active['id'], ['status' => 'active'], null)['status']);
        $this->refused(fn () => $this->service->update('coffee', $active['id'], ['status' => 'pending_dns'], null), 422, 'status');

        $this->assertSame(['enabled', 'disabled', 'created'], array_slice(array_column($this->service->audit('coffee'), 'action'), 0, 3));
    }

    public function testDeleteRemovesTheHostAndKeepsTheAuditTrail(): void
    {
        $tenant = $this->tenant();
        $created = $this->service->create('coffee', $this->input(), ['id' => 1, 'email' => 'a@coffee.test']);
        Event::fake([EntryPointsChanged::class]);

        $this->service->delete('coffee', $created['id'], ['id' => 1, 'email' => 'a@coffee.test']);

        Event::assertDispatched(EntryPointsChanged::class, fn ($e) => $e->tenant->is($tenant));
        $this->assertSame(0, EntryPoint::query()->count());
        $this->assertSame(['deleted', 'created'], array_column($this->service->audit('coffee'), 'action'));
        // the host is free again
        $this->service->create('coffee', $this->input(), null);
    }

    public function testATenantNeverSeesOrChangesAnotherTenantsEntryPoints(): void
    {
        $this->tenant();
        $this->tenant('tea');
        $coffee = $this->service->create('coffee', $this->input(), null);
        $tea = $this->service->create('tea', $this->input(['host' => 'sales.tea.example.test']), null);

        $this->assertSame([$coffee['id']], array_column($this->service->list('coffee'), 'id'));
        $this->assertSame([$tea['id']], array_column($this->service->list('tea'), 'id'));
        $this->refused(fn () => $this->service->show('tea', $coffee['id']), 404);
        $this->refused(fn () => $this->service->update('tea', $coffee['id'], ['label' => 'Mine now'], null), 404);
        $this->refused(fn () => $this->service->delete('tea', $coffee['id'], null), 404);
        $this->refused(fn () => $this->service->list('nobody'), 404);
        $this->assertSame('Sales academy', EntryPoint::query()->findOrFail($coffee['id'])->label);
        $this->assertSame(['created'], array_column($this->service->audit('tea'), 'action'));
        $this->assertSame([], array_filter($this->service->audit('tea'), fn ($row) => $row['entry_point_id'] === $coffee['id']));
    }

    public function testMalformedIdsAreUnknownIds(): void
    {
        $this->tenant();
        foreach (['', '1', 'nope', "01j9z3k8m2x4q7r5t6v8w0y1a'", str_repeat('a', 27)] as $id) {
            $this->refused(fn () => $this->service->show('coffee', $id), 404);
        }
    }

    public function testDeletingATenantRemovesItsEntryPoints(): void
    {
        $tenant = $this->tenant();
        $this->service->create('coffee', $this->input(), null);

        $tenant->delete();

        $this->assertSame(0, EntryPoint::query()->count());
        $this->assertSame(0, EntryPointAudit::query()->count());
    }

    public function testTheVerificationTokenNeverLeavesThroughSerialization(): void
    {
        $this->tenant();
        $created = $this->service->create('coffee', $this->input(), null);

        $this->assertArrayNotHasKey('verification_token', EntryPoint::query()->findOrFail($created['id'])->toArray());
    }
}
