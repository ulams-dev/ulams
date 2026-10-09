<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Ulams\CourseBuilder\Jobs\MoveToNewSiteJob;
use Ulams\CourseBuilder\Models\Fragment;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Source;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Tests\TestCase;
use Ulams\CourseBuilder\Transfer\SessionArchive;
use Ulams\Tenancy\Enums\TenancyPermissionsEnum;
use Ulams\Tenancy\Services\Contracts\TenantCommandRunnerContract;

/** Moving a builder session to another site: the archive round trip and the "new site" flow (ADR 0048). */
class NewSiteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // the tenancy defaults (host patterns); the package is not booted in these tests
        config(['ulams_tenancy' => require __DIR__ . '/../../../tenancy/src/config.php']);
    }

    private function operator()
    {
        $user = $this->author();
        Permission::findOrCreate(TenancyPermissionsEnum::TENANCY_MANAGE, 'api');
        $user->givePermissionTo(TenancyPermissionsEnum::TENANCY_MANAGE);

        return $user;
    }

    private function url(Session $s): string
    {
        return "/api/admin/course-builder/sessions/{$s->id}/new-site";
    }

    private function runner(array &$calls, ?string $fail = null): void
    {
        $this->app->instance(TenantCommandRunnerContract::class, new class ($calls, $fail) implements TenantCommandRunnerContract {
            public function __construct(private array &$calls, private ?string $fail)
            {
            }

            public function run(string $host, array $arguments): string
            {
                $this->calls[] = [$host, $arguments];
                if ($this->fail !== null && $arguments[0] === $this->fail) {
                    throw new RuntimeException("`{$arguments[0]}` failed");
                }

                return $arguments[0] === 'course-builder:session:import' ? "noise\n" . json_encode(['sessionId' => '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'invited' => true]) : 'ok';
            }
        });
    }

    public function testTheArchiveRoundTripKeepsCitationsAndSources(): void
    {
        $author = $this->author();
        $session = $this->toApplyReview($author);
        $path = sys_get_temp_dir() . '/cb-test-' . $session->id . '.tar';
        app(SessionArchive::class)->export($session, $path);

        $oldSource = Source::query()->where('session_id', $session->id)->first();
        $oldFragments = Fragment::query()->where('source_id', $oldSource->id)->orderBy('ordinal')->pluck('content_hash', 'id')->all();
        $original = Version::query()->find($session->current_version_id)->document;
        // another tenant has its own database: fragment ids are unique per database
        Fragment::query()->where('source_id', $oldSource->id)->delete();

        Storage::fake('course_builder_private');
        $other = $this->author();
        $copy = app(SessionArchive::class)->import($path, (int) $other->getKey());
        @unlink($path);

        $this->assertNotSame($session->id, $copy->id);
        $this->assertSame((int) $other->getKey(), $copy->author_id);
        $this->assertSame(Session::APPLY_REVIEW, $copy->status);
        $this->assertNull($copy->course_id);
        $this->assertNull($copy->applied_version_id);
        $this->assertSame($session->brief, $copy->brief);

        $moved = Version::query()->find($copy->current_version_id)->document;
        $newSource = Source::query()->where('session_id', $copy->id)->first();
        $this->assertNotSame($oldSource->id, $newSource->id);
        // identical blueprint, apart from the source ids the document names
        $this->assertSame(
            json_encode($original['modules'], JSON_UNESCAPED_UNICODE),
            json_encode($moved['modules'], JSON_UNESCAPED_UNICODE)
        );
        $this->assertSame($newSource->id, $moved['sources'][0]['id']);
        $this->assertSame($oldFragments, Fragment::query()->where('source_id', $newSource->id)->orderBy('ordinal')->pluck('content_hash', 'id')->all());
        $this->assertTrue(Storage::disk('course_builder_private')->exists($newSource->path));
    }

    public function testAnArchiveThatIsNotOneIsRefused(): void
    {
        $path = sys_get_temp_dir() . '/cb-bad.tar';
        $tar = new \PharData($path);
        $tar->addFromString('manifest.json', '{"format":"other"}');
        $this->expectException(\InvalidArgumentException::class);
        try {
            app(SessionArchive::class)->import($path, 1);
        } finally {
            @unlink($path);
        }
    }

    public function testCommandsExportAndImportAndCreateTheAuthor(): void
    {
        $session = $this->toApplyReview($this->author());
        $path = sys_get_temp_dir() . '/cb-cmd-' . $session->id . '.tar';
        $this->artisan('course-builder:session:export', ['session' => $session->id, 'path' => $path])->assertSuccessful();
        Fragment::query()->whereIn('source_id', Source::query()->where('session_id', $session->id)->pluck('id'))->delete();
        $this->artisan('course-builder:session:import', ['path' => $path])->assertFailed();
        $code = \Illuminate\Support\Facades\Artisan::call('course-builder:session:import', ['path' => $path, '--author-email' => 'new.author@example.test', '--author-name' => 'New Author']);
        $this->assertSame(0, $code, \Illuminate\Support\Facades\Artisan::output());
        @unlink($path);

        $user = config('auth.providers.users.model')::query()->where('email', 'new.author@example.test')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('admin'));
        $this->assertSame(1, Session::query()->where('author_id', $user->getKey())->count());
    }

    public function testNewSiteIsOffByDefaultAndNeedsThePermission(): void
    {
        $author = $this->author();
        $session = $this->toApplyReview($author);
        $this->actingAs($author, 'api')->postJson($this->url($session), ['slug' => 'brewhouse'])->assertForbidden();

        config(['ulams_tenancy.new_sites' => true]);
        $this->actingAs($author, 'api')->postJson($this->url($session), ['slug' => 'brewhouse'])->assertForbidden();
        $this->actingAs($this->operator(), 'api')->postJson($this->url($session), ['slug' => 'brewhouse'])->assertForbidden(); // not their session
    }

    public function testStartValidatesAndQueuesTheMove(): void
    {
        config(['ulams_tenancy.new_sites' => true]);
        $operator = $this->operator();
        $session = $this->toApplyReview($operator);
        Queue::fake();

        $this->actingAs($operator, 'api')->postJson($this->url($session), ['slug' => 'Bad Slug'])->assertStatus(422);
        $r = $this->actingAs($operator, 'api')->postJson($this->url($session), ['slug' => 'brewhouse']);
        $this->assertSame(202, $r->status(), $r->getContent());
        $r->assertJsonPath('data.status', 'queued');
        Queue::assertPushed(MoveToNewSiteJob::class, fn ($job) => $job->sessionId === $session->id);
        $this->actingAs($operator, 'api')->postJson($this->url($session), ['slug' => 'brewhouse'])->assertStatus(422); // already queued
        $this->actingAs($operator, 'api')->getJson("/api/admin/course-builder/sessions/{$session->id}")->assertJsonPath('data.canCreateSite', true)->assertJsonPath('data.newSite.slug', 'brewhouse');
    }

    public function testTheJobProvisionsTransfersAndReportsTheNewStudio(): void
    {
        config(['ulams_tenancy.new_sites' => true, 'queue.default' => 'sync']);
        $calls = [];
        $this->runner($calls);
        $operator = $this->operator();
        $session = $this->toApplyReview($operator);
        $this->actingAs($operator, 'api')->putJson("/api/admin/course-builder/sessions/{$session->id}/brief", ['brief' => ['theme' => ['preset' => 'nightsky', 'accent' => '#ffaa00']]])->assertOk();

        $this->actingAs($operator, 'api')->postJson($this->url($session), ['slug' => 'brewhouse', 'name' => 'Brew House'])->assertStatus(202);

        $state = $session->refresh()->stateValue('newSite');
        $this->assertSame('done', $state['status'], (string) ($state['error'] ?? ''));
        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $state['sessionId']);
        $this->assertStringEndsWith('/studio/s/01ARZ3NDEKTSV4RRFFQ69G5FAV', $state['studioUrl']);
        $this->assertTrue($state['invited']);
        $this->assertSame('ulams:tenant:create', $calls[0][1][0]);
        $this->assertContains('--theme=nightsky', $calls[0][1]);
        $this->assertContains('--accent=#ffaa00', $calls[0][1]);
        $this->assertContains('--name=Brew House', $calls[0][1]);
        $this->assertSame('brewhouse.localhost', $calls[1][0]);
        $this->assertContains('--author-email=' . $operator->email, $calls[1][1]);
    }

    public function testAFailedProvisioningIsReportedAndCanBeRetried(): void
    {
        config(['ulams_tenancy.new_sites' => true, 'queue.default' => 'sync']);
        $calls = [];
        $this->runner($calls, 'ulams:tenant:create');
        $operator = $this->operator();
        $session = $this->toApplyReview($operator);

        $this->actingAs($operator, 'api')->postJson($this->url($session), ['slug' => 'brewhouse'])->assertStatus(202);
        $state = $session->refresh()->stateValue('newSite');
        $this->assertSame('failed', $state['status']);
        $this->assertStringContainsString('failed', $state['error']);

        $calls = [];
        $this->runner($calls);
        $this->actingAs($operator, 'api')->postJson($this->url($session), ['slug' => 'brewhouse'])->assertStatus(202);
        $this->assertSame('done', $session->refresh()->stateValue('newSite')['status']);
    }
}
