<?php

namespace Ulams\Tenancy\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Ulams\Auth\Support\ReturnUrlPolicy;
use Ulams\Core\Http\Middleware\EnforceTrustedOrigin;
use Ulams\Core\Support\EntryFronts;
use Ulams\Interactive\Services\InteractiveCsp;
use Ulams\Tenancy\Models\EntryHostPublication;
use Ulams\Tenancy\Models\PublishedEntryHost;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Services\Contracts\EntryHostPublisherContract;
use Ulams\Tenancy\Services\EntryHostPublisher;
use Ulams\Tenancy\Services\EntryHostStore;
use Ulams\Tenancy\Services\EntryPointService;
use Ulams\Tenancy\Support\TenantNaming;
use Ulams\Tenancy\Tests\Mocks\InProcessTenantRunner;
use Ulams\Tenancy\Tests\TestCase;

/**
 * Entry hosts become trusted origins, return URLs, frame ancestors and hand-off targets of their own academy and of
 * nobody else, and stop being so the moment they are disabled or deleted (ADR 0195, EP-3).
 */
class EntryHostsPublicationTest extends TestCase
{
    private InProcessTenantRunner $runner;
    private EntryHostPublisher $publisher;
    private EntryPointService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'ulams_tenancy.entry_trusted_suffixes' => ['.example.test'],
            'app.frontend_url' => 'http://coffee.app.localhost',
            'ulams.core.security.admin_url' => 'http://coffee.admin.localhost',
            'ulams.core.security.trusted_origins' => [],
            'ulams.core.security.trust_localhost_outside_production' => false,
            'app.url' => 'http://coffee.localhost',
        ]);
        foreach (['coffee', 'tea'] as $slug) {
            Tenant::query()->create(array_merge(TenantNaming::newTenantAttributes($slug), ['status' => Tenant::STATUS_ACTIVE]));
        }
        $this->runner = new InProcessTenantRunner('coffee');
        $this->publisher = new EntryHostPublisher($this->runner);
        $this->app->instance(EntryHostPublisherContract::class, $this->publisher);
        $this->app->instance(EntryHostPublisher::class, $this->publisher);
        $this->app->forgetInstance(EntryPointService::class);
        $this->service = app(EntryPointService::class);
    }

    private function create(string $slug, string $key, array $extra = []): array
    {
        return $this->service->create($slug, array_merge(['host' => "{$key}.{$slug}.example.test", 'key' => $key, 'kind' => 'academy', 'label' => ucfirst($key)], $extra), null);
    }

    private function origin(string $url): bool
    {
        app(EntryFronts::class)->forget();

        return app(EnforceTrustedOrigin::class)->isTrusted($url);
    }

    public function testAnActiveEntryHostIsTrustedAfterTheChangeAndNotBefore(): void
    {
        $this->assertFalse($this->origin('http://sales.coffee.example.test'));

        $this->create('coffee', 'sales');

        $this->assertTrue($this->origin('http://sales.coffee.example.test'));
        $this->assertSame(['http://sales.coffee.example.test'], PublishedEntryHost::query()->pluck('origin')->all());
        // exact origin: another scheme, port or host is not it
        foreach (['https://sales.coffee.example.test', 'http://sales.coffee.example.test:8443', 'http://evil.coffee.example.test', 'http://sales.coffee.example.test.evil.test'] as $other) {
            $this->assertFalse($this->origin($other), $other);
        }
    }

    public function testDisablingAndDeletingRemoveTheHostAtOnce(): void
    {
        $entry = $this->create('coffee', 'sales');
        $this->assertTrue($this->origin('http://sales.coffee.example.test'));

        $this->service->update('coffee', $entry['id'], ['status' => 'disabled'], null);
        $this->assertFalse($this->origin('http://sales.coffee.example.test'));

        $this->service->update('coffee', $entry['id'], ['status' => 'active'], null);
        $this->assertTrue($this->origin('http://sales.coffee.example.test'));

        $this->service->delete('coffee', $entry['id'], null);
        $this->assertFalse($this->origin('http://sales.coffee.example.test'));
        $this->assertSame(0, PublishedEntryHost::query()->count());
    }

    public function testAPendingHostIsNotTrusted(): void
    {
        config(['ulams_tenancy.entry_trusted_suffixes' => []]);
        $this->create('coffee', 'custom', ['host' => 'learn.coffee.example.org']);

        $this->assertFalse($this->origin('http://learn.coffee.example.org'));
        $this->assertSame(0, PublishedEntryHost::query()->count());
        $this->assertSame([], $this->runner->calls, 'nothing to publish while the list of active hosts is unchanged');
    }

    public function testReturnUrlsAndFrameAncestorsFollowTheSameList(): void
    {
        $policy = app(ReturnUrlPolicy::class);
        $this->assertFalse($policy->isAllowed('http://sales.coffee.example.test/learn/1'));

        $entry = $this->create('coffee', 'sales');
        app(EntryFronts::class)->forget();
        $this->assertTrue($policy->isAllowed('http://sales.coffee.example.test/learn/1'));
        $this->assertStringContainsString('http://sales.coffee.example.test', InteractiveCsp::frameAncestors());
        // a content origin or another host of the academy's domain never rides along
        $this->assertFalse($policy->isAllowed('http://sales.coffee.example.test.evil.test/'));
        $this->assertFalse($policy->isAllowed('http://tea.example.test/'));

        $this->service->delete('coffee', $entry['id'], null);
        app(EntryFronts::class)->forget();
        $this->assertFalse($policy->isAllowed('http://sales.coffee.example.test/learn/1'));
        $this->assertStringNotContainsString('sales.coffee.example.test', InteractiveCsp::frameAncestors());
    }

    public function testFrontHostsAreTheAcademyAndItsActiveEntryHosts(): void
    {
        $this->create('coffee', 'sales');
        app(EntryFronts::class)->forget();
        $fronts = app(EntryFronts::class);

        $this->assertEqualsCanonicalizing(['coffee.app.localhost', 'sales.coffee.example.test'], $fronts->frontHosts());
        $this->assertTrue($fronts->isFrontHost('SALES.coffee.example.test'));
        // the API and the admin are not fronts a session may be handed to
        $this->assertFalse($fronts->isFrontHost('coffee.localhost'));
        $this->assertFalse($fronts->isFrontHost('coffee.admin.localhost'));
    }

    public function testAnotherAcademysEntryHostsAreNeverPublishedHere(): void
    {
        $this->create('tea', 'sales');
        $this->create('coffee', 'learn');

        // tea's list went to tea's own host and holds tea's entry point only; coffee's copy holds coffee's
        $tea = array_values(array_filter($this->runner->calls, fn ($c) => $c['host'] === 'tea.localhost'));
        $this->assertCount(1, $tea);
        $payload = json_decode(base64_decode(substr($tea[0]['arguments'][1], strlen('--payload='))), true);
        $this->assertSame(['sales.tea.example.test'], array_column($payload, 'host'));
        $this->assertSame(['http://learn.coffee.example.test'], PublishedEntryHost::query()->pluck('origin')->all());
        $this->assertFalse($this->origin('http://sales.tea.example.test'));
    }

    public function testAnUnchangedListIsNotPublishedAgain(): void
    {
        $this->create('coffee', 'sales');
        $this->assertCount(1, $this->runner->calls);

        $this->publisher->publish(Tenant::query()->where('slug', 'coffee')->firstOrFail());
        $this->assertCount(1, $this->runner->calls);
        $this->publisher->publish(Tenant::query()->where('slug', 'coffee')->firstOrFail(), true);
        $this->assertCount(2, $this->runner->calls);
    }

    public function testTheCommandReachesTheAcademyByItsApiHostWithTheOriginsOnly(): void
    {
        $this->create('coffee', 'sales');

        $call = $this->runner->calls[0];
        $this->assertSame('coffee.localhost', $call['host']);
        $this->assertSame('ulams:entry-points:publish', $call['arguments'][0]);
        $payload = json_decode(base64_decode(substr($call['arguments'][1], strlen('--payload='))), true);
        $this->assertSame([['host' => 'sales.coffee.example.test', 'origin' => 'http://sales.coffee.example.test', 'kind' => 'academy', 'key' => 'sales']], $payload);
    }

    public function testAFailedPublicationIsReportedAndFoundAgainByTheReconcileJob(): void
    {
        $this->runner->fail = true;
        try {
            $this->create('coffee', 'sales');
            $this->fail('A change that could not be published looked like a success.');
        } catch (\Ulams\Tenancy\Exceptions\EntryPointException $e) {
            $this->assertSame(503, $e->status);
        }
        // saved on the platform, not yet trusted by the academy
        $this->assertSame(1, \Ulams\Tenancy\Models\EntryPoint::query()->count());
        $this->assertFalse($this->origin('http://sales.coffee.example.test'));
        $this->assertNull(EntryHostPublication::query()->first());

        $this->runner->fail = false;
        $result = $this->publisher->reconcile();

        $this->assertSame(['coffee'], $result['published']);
        $this->assertSame([], $result['failed']);
        $this->assertTrue($this->origin('http://sales.coffee.example.test'));
        // and now it is quiet
        $this->assertSame(['published' => [], 'failed' => []], $this->publisher->reconcile());
    }

    public function testRetryingAnUnchangedUpdateRefreshesTheAcademy(): void
    {
        $entry = $this->create('coffee', 'sales');
        $this->runner->fail = true;
        try {
            $this->service->update('coffee', $entry['id'], ['status' => 'disabled'], null);
            $this->fail('Expected the publication to fail.');
        } catch (\Ulams\Tenancy\Exceptions\EntryPointException) {
        }
        $this->assertTrue($this->origin('http://sales.coffee.example.test'), 'the academy still has the old list');

        $this->runner->fail = false;
        $this->service->update('coffee', $entry['id'], ['status' => 'disabled'], null);

        $this->assertFalse($this->origin('http://sales.coffee.example.test'));
    }

    public function testReconcileRepublishesAnAcademyWhoseLastEntryPointWasDeleted(): void
    {
        $entry = $this->create('coffee', 'sales');
        $this->runner->fail = true;
        try {
            $this->service->delete('coffee', $entry['id'], null);
        } catch (\Ulams\Tenancy\Exceptions\EntryPointException) {
        }
        $this->assertTrue($this->origin('http://sales.coffee.example.test'), 'the academy still trusts the deleted host');

        $this->runner->fail = false;
        $this->assertSame(['coffee'], $this->publisher->reconcile()['published']);
        $this->assertFalse($this->origin('http://sales.coffee.example.test'));
    }

    public function testTheReconcileCommandReportsFailuresInItsExitCode(): void
    {
        $this->runner->fail = true;
        try {
            $this->create('coffee', 'sales');
        } catch (\Ulams\Tenancy\Exceptions\EntryPointException) {
        }

        $this->assertSame(1, Artisan::call('ulams:entry-points:reconcile'));
        $this->assertStringContainsString('coffee: the academy did not answer', Artisan::output());
        $this->runner->fail = false;
        $this->assertSame(0, Artisan::call('ulams:entry-points:reconcile', ['--tenant' => ['coffee']]));
    }

    public function testThePublishCommandRefusesAnythingThatIsNotAPlainOrigin(): void
    {
        config(['ulams_tenancy.tenant_slug' => 'coffee']);
        $bad = [
            ['host' => 'sales.coffee.example.test', 'origin' => 'javascript:alert(1)'],
            ['host' => 'sales.coffee.example.test', 'origin' => 'http://evil.example.test'],
            ['host' => 'sales.coffee.example.test', 'origin' => 'http://sales.coffee.example.test/path'],
            ['host' => 'sales.coffee.example.test', 'origin' => 'http://user@sales.coffee.example.test'],
            ['host' => '10.0.0.1', 'origin' => 'http://10.0.0.1'],
            ['host' => 'a.content.example.test', 'origin' => 'http://a.content.example.test'],
            'not a row',
        ];
        foreach ($bad as $row) {
            $this->assertSame(1, Artisan::call('ulams:entry-points:publish', ['--payload' => base64_encode(json_encode([$row]))]), json_encode($row));
        }
        $this->assertSame(1, Artisan::call('ulams:entry-points:publish', ['--payload' => 'not base64 json']));
        $this->assertSame(0, PublishedEntryHost::query()->count());
    }

    public function testThePublishCommandReplacesTheListAndRefusesToRunOnThePlatform(): void
    {
        config(['ulams_tenancy.tenant_slug' => null]);
        $this->assertSame(1, Artisan::call('ulams:entry-points:publish', ['--payload' => base64_encode('[]')]));

        config(['ulams_tenancy.tenant_slug' => 'coffee']);
        $row = fn (string $key) => ['host' => "{$key}.coffee.example.test", 'origin' => "http://{$key}.coffee.example.test", 'kind' => 'academy', 'key' => $key];
        Artisan::call('ulams:entry-points:publish', ['--payload' => base64_encode(json_encode([$row('a'), $row('b')]))]);
        $this->assertEqualsCanonicalizing(['a.coffee.example.test', 'b.coffee.example.test'], PublishedEntryHost::query()->pluck('host')->all());

        Artisan::call('ulams:entry-points:publish', ['--payload' => base64_encode(json_encode([$row('b')]))]);
        $this->assertSame(['b.coffee.example.test'], PublishedEntryHost::query()->pluck('host')->all());
        Artisan::call('ulams:entry-points:publish', ['--payload' => base64_encode('[]')]);
        $this->assertSame([], (new EntryHostStore())->origins());
    }

    public function testNoHostIsTrustedBeforeTheMigrationHasRun(): void
    {
        \Illuminate\Support\Facades\Schema::drop('tenant_entry_hosts');
        EntryHostStore::forgetSchemaCheck();

        $this->assertSame([], (new EntryHostStore())->origins());
        $this->assertFalse($this->origin('http://sales.coffee.example.test'));
    }
}
