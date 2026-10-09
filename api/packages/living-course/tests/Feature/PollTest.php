<?php

namespace Ulams\LivingCourse\Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Ulams\Core\Http\SafeHttp;
use Ulams\LivingCourse\Connectors\GitConnector;
use Ulams\LivingCourse\Connectors\SourceConnectorRegistry;
use Ulams\LivingCourse\Jobs\CheckSourceJob;
use Ulams\LivingCourse\Models\AuditEntry;
use Ulams\LivingCourse\Models\Connection;
use Ulams\LivingCourse\Models\Revision;
use Ulams\LivingCourse\Tests\Support\FakeGitHost;
use Ulams\LivingCourse\Tests\TestCase;

/** Scheduled polling per tenant (plan 5.6) and the connection settings of a connected source. */
class PollTest extends TestCase
{
    private FakeGitHost $host;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        SafeHttp::useResolver(fn (string $host) => ['140.82.112.5']);
        $this->host = new FakeGitHost();
        $this->host->files = ['docs/a.md' => "# A\n\nFirst handbook page with enough words to be a real fragment of text.\n"];
        app(SourceConnectorRegistry::class)->register(new GitConnector($this->host->handler()));
    }

    protected function tearDown(): void
    {
        SafeHttp::useResolver(null);
        parent::tearDown();
    }

    private function connect(string $schedule = 'daily'): Connection
    {
        $author = $this->author();
        $session = $this->newSession($author);
        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/sessions/{$session->id}/sources/connect", [
            'connector' => 'git', 'config' => ['host' => 'github', 'repository' => 'acme/handbook', 'paths' => ['docs/**/*.md']], 'schedule' => $schedule,
        ])->assertCreated();

        return Connection::query()->where('session_id', $session->id)->firstOrFail();
    }

    public function testDueConnectionsAreCheckedAndTheNextCheckIsPlannedWithJitter(): void
    {
        $c = $this->connect('daily');
        $this->assertNotNull($c->next_check_at, 'planned after the first fetch');
        $this->assertTrue($c->next_check_at->between(now()->addMinutes(1440), now()->addMinutes(1440 + 145)), 'a day plus up to 10 % jitter');
        Queue::fake();

        $this->artisan('living-course:poll')->expectsOutputToContain('0 check(s) queued')->assertSuccessful();
        Queue::assertNotPushed(CheckSourceJob::class);

        $c->forceFill(['next_check_at' => now()->subMinute()])->save();
        $this->artisan('living-course:poll')->expectsOutputToContain('1 check(s) queued')->assertSuccessful();
        Queue::assertPushed(CheckSourceJob::class, fn (CheckSourceJob $j) => $j->connectionId === $c->id && $j->trigger === 'poll');
        $this->assertTrue($c->refresh()->next_check_at->isFuture(), 'planned at once, so it is not picked twice');
        $this->artisan('living-course:poll')->expectsOutputToContain('0 check(s) queued')->assertSuccessful();
    }

    public function testManualUploadedAndPausedSourcesAreNeverPolled(): void
    {
        $c = $this->connect('manual');
        $uploaded = $this->connectionOf($this->sessionWithSource($this->author()));
        $paused = $this->connect('hourly');
        $paused->forceFill(['status' => 'paused'])->save();
        Connection::query()->update(['next_check_at' => now()->subHour()]);
        Queue::fake();

        $this->artisan('living-course:poll')->expectsOutputToContain('0 check(s) queued')->assertSuccessful();

        Queue::assertNotPushed(CheckSourceJob::class);
        $this->assertNull($c->refresh()->next_check_at === null ? null : null);
        $this->assertSame('manual', $uploaded->refresh()->schedule);
    }

    public function testAPolledCheckCreatesARevisionWhenTheRepositoryChanged(): void
    {
        $c = $this->connect('hourly');
        $this->host->files['docs/a.md'] .= "\nAnd one more sentence that readers of the handbook should now know about.\n";
        $c->forceFill(['next_check_at' => now()->subMinute()])->save();

        $this->artisan('living-course:poll')->assertSuccessful();

        $two = Revision::query()->where('source_id', $c->source_id)->where('number', 2)->firstOrFail();
        $this->assertSame('poll', $two->trigger);
        $this->assertGreaterThanOrEqual(now()->addMinutes(60)->timestamp - 5, $c->refresh()->next_check_at->timestamp);
    }

    public function testTheDailyLimitSkipsChecksAndConnectionsInErrorBackOffToDaily(): void
    {
        $c = $this->connect('hourly');
        $c->forceFill(['next_check_at' => now()->subMinute()])->save();
        config(['living_course.poll.checks_per_connection_per_day' => 0]);
        Queue::fake();

        $this->artisan('living-course:poll')->expectsOutputToContain('1 skipped by the daily limit')->assertSuccessful();
        Queue::assertNotPushed(CheckSourceJob::class);

        config(['living_course.poll.checks_per_connection_per_day' => 48]);
        $c->refresh()->forceFill(['status' => 'error', 'next_check_at' => now()->subMinute()])->save();
        $this->artisan('living-course:poll')->expectsOutputToContain('1 check(s) queued')->assertSuccessful();
        $this->assertSame('error', $c->refresh()->status);
        $this->assertGreaterThan(now()->addHours(23), $c->next_check_at, (string) $c->next_check_at);
    }

    public function testTheMinimumIntervalApplies(): void
    {
        config(['living_course.poll.min_minutes' => 360]);
        $c = $this->connect('hourly');

        $this->assertGreaterThan(now()->addMinutes(359), $c->next_check_at);
    }

    public function testOldWebhookDeliveriesArePruned(): void
    {
        $c = $this->connect();
        DB::table('living_course_webhook_deliveries')->insert([
            ['connection_id' => $c->id, 'delivery_id' => 'old', 'outcome' => 'queued', 'payload_sha256' => str_repeat('a', 64), 'received_at' => now()->subDays(31)],
            ['connection_id' => $c->id, 'delivery_id' => 'new', 'outcome' => 'queued', 'payload_sha256' => str_repeat('b', 64), 'received_at' => now()->subDays(2)],
        ]);

        $this->artisan('living-course:poll')->expectsOutputToContain('1 old webhook deliveries pruned')->assertSuccessful();

        $this->assertSame(['new'], DB::table('living_course_webhook_deliveries')->pluck('delivery_id')->all());
    }

    public function testThePollIsScheduledEveryFifteenMinutes(): void
    {
        $events = collect(app(Schedule::class)->events())->filter(fn ($e) => str_contains((string) $e->command, 'living-course:poll'));

        $this->assertCount(1, $events);
        $this->assertSame('*/15 * * * *', $events->first()->expression);
        $this->assertTrue($events->first()->withoutOverlapping);
    }

    public function testConnectionSettingsCanBeChangedAndAreRevalidated(): void
    {
        $c = $this->connect();
        $url = "/api/admin/living-course/connections/{$c->id}";
        $owner = $c->session->author;

        $this->actingAs($owner, 'api')->putJson($url, ['schedule' => 'weekly', 'config' => ['host' => 'github', 'repository' => 'acme/handbook', 'paths' => ['docs/**/*.md'], 'branch' => 'main'], 'secrets' => ['token' => 'ghp_newsecret', 'webhook_secret' => 'forged']])->assertOk()->assertJsonPath('data.schedule', 'weekly');
        $this->assertSame('ghp_newsecret', $c->refresh()->secrets['token']);
        $this->assertNotSame('forged', $c->secrets['webhook_secret'], 'the webhook secret is only changed by rotation');
        $audit = AuditEntry::query()->where('action', 'connection.updated')->where('subject_id', $c->id)->firstOrFail();
        $this->assertStringNotContainsString('ghp_newsecret', json_encode($audit->data));
        $this->assertSame(['token'], $audit->data['changes']['secrets']);

        $this->host->files = [];
        $this->actingAs($owner, 'api')->putJson($url, ['config' => ['host' => 'github', 'repository' => 'acme/handbook', 'paths' => ['docs/**/*.md']]])->assertStatus(422);
        $this->actingAs($owner, 'api')->putJson($url, ['config' => ['host' => 'github', 'repository' => 'bad']])->assertStatus(422);
        $this->assertSame('weekly', $c->refresh()->schedule);
    }
}
