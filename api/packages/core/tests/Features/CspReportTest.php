<?php

namespace Ulams\Core\Tests\Features;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Ulams\Core\Models\CspReport;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Core\Tests\TestCase;

/** The CSP report collector and its admin list (ADR 0044). */
class CspReportTest extends TestCase
{
    use CreatesUsers, DatabaseTransactions;

    private function legacy(array $overrides = []): array
    {
        return ['csp-report' => array_merge([
            'document-uri' => 'http://coffee.app.localhost:4321/learn/1/2?token=secret#frag',
            'effective-directive' => 'frame-src',
            'violated-directive' => "frame-src 'self'",
            'blocked-uri' => 'https://tool.example.test/lti/login?login_hint=secret',
        ], $overrides)];
    }

    private function send(string $type, mixed $payload, array $server = [])
    {
        return $this->call('POST', 'api/csp-report', [], [], [], array_merge(['CONTENT_TYPE' => $type], $server), is_string($payload) ? $payload : json_encode($payload));
    }

    public function test_a_report_uri_report_is_stored_without_urls_or_query_strings(): void
    {
        $this->send('application/csp-report', $this->legacy())->assertNoContent();

        $row = CspReport::query()->firstOrFail();
        $this->assertSame('frame-src', $row->directive);
        $this->assertSame('tool.example.test', $row->blocked_host);
        $this->assertSame('/learn/1/2', $row->document_path);
        $this->assertSame(1, $row->count);
        $this->assertStringNotContainsString('secret', json_encode($row->toArray()));
    }

    public function test_reporting_api_batches_are_stored_one_row_per_violation(): void
    {
        $this->send('application/reports+json', [
            ['type' => 'csp-violation', 'url' => 'http://x', 'body' => [
                'documentURL' => 'http://coffee.app.localhost/learn/1/2',
                'effectiveDirective' => 'script-src-elem',
                'blockedURL' => 'inline',
            ]],
            ['type' => 'csp-violation', 'body' => [
                'documentURL' => 'http://coffee.app.localhost/learn/1/2',
                'effectiveDirective' => 'img-src',
                'blockedURL' => 'http://cdn.example.test:8080/a.png',
            ]],
            ['type' => 'deprecation', 'body' => ['id' => 'x']],
        ])->assertNoContent();

        $this->assertSame(2, CspReport::query()->count());
        $this->assertSame('inline', CspReport::query()->where('directive', 'script-src-elem')->value('blocked_host'));
        $this->assertSame('cdn.example.test:8080', CspReport::query()->where('directive', 'img-src')->value('blocked_host'));
    }

    public function test_repeated_violations_are_aggregated_with_a_counter(): void
    {
        $this->send('application/csp-report', $this->legacy())->assertNoContent();
        $this->send('application/csp-report', $this->legacy(['blocked-uri' => 'https://tool.example.test/other?x=1']))->assertNoContent();
        $this->send('application/csp-report', $this->legacy(['document-uri' => 'http://coffee.app.localhost/learn/3/4']))->assertNoContent();

        $this->assertSame(2, CspReport::query()->count());
        $this->assertSame(2, CspReport::query()->where('document_path', '/learn/1/2')->value('count'));
        $row = CspReport::query()->where('document_path', '/learn/1/2')->first();
        $this->assertTrue($row->last_seen_at >= $row->first_seen_at);
    }

    public function test_keyword_and_schemeless_blocked_sources_are_kept_as_such(): void
    {
        foreach (['inline', 'eval', 'data:text/html;base64,AAAA', 'blob:http://x/abc', ''] as $blocked) {
            $this->send('application/csp-report', $this->legacy(['blocked-uri' => $blocked]))->assertNoContent();
        }

        $this->assertSame(['blob', 'data', 'eval', 'inline'], CspReport::query()->orderBy('blocked_host')->pluck('blocked_host')->all());
    }

    public function test_garbage_is_not_stored(): void
    {
        $this->send('application/csp-report', $this->legacy(['effective-directive' => "x'; drop table"]))->assertNoContent();
        $this->send('application/csp-report', ['csp-report' => ['blocked-uri' => 'x']])->assertNoContent();
        $this->send('application/csp-report', ['csp-report' => 'nope'])->assertNoContent();
        $this->send('application/reports+json', [['type' => 'csp-violation', 'body' => 'nope'], 'x', 5])->assertNoContent();

        $this->assertSame(0, CspReport::query()->count());
    }

    public function test_long_values_are_truncated(): void
    {
        $this->send('application/csp-report', $this->legacy([
            'document-uri' => 'http://coffee.app.localhost/' . str_repeat('a', 400),
            'blocked-uri' => 'https://' . str_repeat('b', 400) . '.example.test/x',
        ]))->assertNoContent();

        $row = CspReport::query()->firstOrFail();
        $this->assertSame(255, strlen($row->document_path));
        $this->assertLessThanOrEqual(255, strlen($row->blocked_host));
    }

    public function test_a_report_request_is_limited_to_twenty_violations(): void
    {
        $batch = [];
        for ($i = 0; $i < 30; $i++) {
            $batch[] = ['type' => 'csp-violation', 'body' => ['documentURL' => "http://a.test/p{$i}", 'effectiveDirective' => 'img-src', 'blockedURL' => 'http://b.test/x']];
        }

        $this->send('application/reports+json', $batch)->assertNoContent();

        $this->assertSame(20, CspReport::query()->count());
    }

    public function test_the_body_size_content_type_and_json_are_checked(): void
    {
        $this->send('application/csp-report', str_repeat('x', 16 * 1024 + 1))->assertStatus(413);
        $this->send('text/plain', json_encode($this->legacy()))->assertStatus(415);
        $this->send('application/csp-report', 'not json')->assertStatus(400);
        $this->send('application/csp-report', '"a string"')->assertStatus(400);
        $this->assertSame(0, CspReport::query()->count());
    }

    public function test_the_collector_is_rate_limited_per_ip(): void
    {
        $statuses = [];
        for ($i = 0; $i < 62; $i++) {
            $statuses[] = $this->send('application/csp-report', $this->legacy())->getStatusCode();
        }

        $this->assertSame(60, count(array_filter($statuses, fn ($s) => $s === 204)));
        $this->assertSame(429, end($statuses));
    }

    public function test_content_origins_and_sandboxed_frames_may_report_without_credentials(): void
    {
        // the Origin check exempts the collector: package code on the content origin is the main subject
        $this->app['env'] = 'production';
        config(['ulams.core.security.origin_check' => true]);

        foreach (['http://coffee.content.localhost', 'null'] as $origin) {
            $this->send('application/csp-report', $this->legacy(), ['HTTP_ORIGIN' => $origin])->assertNoContent();
        }
    }

    public function test_only_admins_read_the_reports(): void
    {
        CspReport::query()->create(['directive' => 'frame-src', 'blocked_host' => 'a.test', 'document_path' => '/', 'count' => 3, 'first_seen_at' => now(), 'last_seen_at' => now()]);

        $this->getJson('api/admin/csp-reports')->assertUnauthorized();
        $this->actingAs($this->makeStudent(), 'api')->getJson('api/admin/csp-reports')->assertForbidden();
        $this->actingAs($this->makeInstructor(), 'api')->getJson('api/admin/csp-reports')->assertForbidden();

        $this->actingAs($this->makeAdmin(), 'api')->getJson('api/admin/csp-reports')
            ->assertOk()
            ->assertJsonPath('data.0.directive', 'frame-src')
            ->assertJsonPath('data.0.blocked_host', 'a.test')
            ->assertJsonPath('data.0.count', 3);
    }

    public function test_prune_deletes_what_was_not_seen_for_thirty_days(): void
    {
        $row = fn (string $host, int $daysAgo) => CspReport::query()->create([
            'directive' => 'img-src', 'blocked_host' => $host, 'document_path' => '/', 'count' => 1,
            'first_seen_at' => now()->subDays($daysAgo + 1), 'last_seen_at' => now()->subDays($daysAgo),
        ]);
        $row('old.test', 31);
        $row('fresh.test', 29);

        $this->artisan('csp-reports:prune')->expectsOutputToContain('Deleted 1 CSP report row(s) older than 30 days')->assertSuccessful();

        $this->assertSame(['fresh.test'], CspReport::query()->pluck('blocked_host')->all());
    }
}
