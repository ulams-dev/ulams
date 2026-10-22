<?php

namespace Ulams\Core\Tests\Features;

use Illuminate\Support\Facades\Route;
use Ulams\Core\Support\EntryFronts;
use Ulams\Core\Tests\TestCase;

/**
 * The extra front hosts of an academy (entry points, ADR 0195): one registry that the origin check, the return URLs,
 * the frame ancestors and the session hand-off read.
 */
class EntryFrontsTest extends TestCase
{
    private const SALES = 'https://sales.coffee.example.test';

    /** @var list<string> */
    private array $active = [self::SALES];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.url' => 'https://coffee.api.example.test',
            'app.frontend_url' => 'https://coffee.example.test/email',
            'ulams.core.security.admin_url' => 'https://coffee.admin.example.test',
            'ulams.core.security.trusted_origins' => [],
        ]);
        $this->app['env'] = 'production';
        Route::any('api/test/write', fn () => response()->json(['ok' => true]));
        app(EntryFronts::class)->addSource(fn () => $this->active);
    }

    private function write(string $origin)
    {
        app(EntryFronts::class)->forget();

        return $this->call('POST', 'api/test/write', [], [], [], ['HTTP_ORIGIN' => $origin]);
    }

    public function testAnActiveEntryHostMayWriteAndOthersOfTheSameDomainMayNot(): void
    {
        $this->write(self::SALES)->assertOk();
        foreach (['https://hr.coffee.example.test', 'http://sales.coffee.example.test', 'https://sales.coffee.example.test:8443', 'https://sales.coffee.example.test.evil.test'] as $origin) {
            $this->write($origin)->assertForbidden();
        }
    }

    public function testAnEntryHostThatIsNoLongerActiveMayNotWriteAtOnce(): void
    {
        $this->write(self::SALES)->assertOk();

        $this->active = [];

        $this->write(self::SALES)->assertForbidden();
    }

    public function testAContentOriginNeverBecomesATrustedFrontThroughTheRegistry(): void
    {
        $this->active = ['https://coffee.content.example.test'];

        $this->write('https://coffee.content.example.test')->assertForbidden();
    }

    public function testTheRegistryListsOriginsAndFrontHostNames(): void
    {
        $fronts = app(EntryFronts::class);
        $this->active = [self::SALES, self::SALES, '', 'https://learn.coffee.example.test:8443'];
        $fronts->forget();

        $this->assertSame([self::SALES, 'https://learn.coffee.example.test:8443'], $fronts->origins());
        $this->assertEqualsCanonicalizing(['coffee.example.test', 'sales.coffee.example.test', 'learn.coffee.example.test'], $fronts->frontHosts());
        $this->assertTrue($fronts->isFrontHost(' Sales.Coffee.Example.Test '));
        $this->assertFalse($fronts->isFrontHost('coffee.admin.example.test'));
        $this->assertFalse($fronts->isFrontHost('coffee.api.example.test'));
    }

    public function testWithoutASourceThereAreNoEntryHostsAndTheAcademyKeepsItsOwnFront(): void
    {
        $fronts = new EntryFronts();

        $this->assertSame([], $fronts->origins());
        $this->assertSame(['coffee.example.test'], $fronts->frontHosts());
    }

    public function testTheListIsReadOncePerRequestUntilForgotten(): void
    {
        $reads = 0;
        $fronts = new EntryFronts();
        $fronts->addSource(function () use (&$reads) {
            $reads++;

            return [self::SALES];
        });

        $fronts->origins();
        $fronts->frontHosts();
        $fronts->isFrontHost('sales.coffee.example.test');
        $this->assertSame(1, $reads);

        $fronts->forget();
        $fronts->origins();
        $this->assertSame(2, $reads);
    }
}
