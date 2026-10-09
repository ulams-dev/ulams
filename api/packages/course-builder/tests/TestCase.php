<?php

namespace Ulams\CourseBuilder\Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Ulams\Ai\Contracts\LlmDriver;
use Ulams\Ai\Drivers\FakeDriver;
use Ulams\Ai\UlamsAiServiceProvider;
use Ulams\Auth\Tests\Models\Client;
use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\CourseBuilder\Database\Seeders\CourseBuilderPermissionSeeder;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\UlamsCourseBuilderServiceProvider;
use Ulams\Courses\AuthServiceProvider as CoursesAuthServiceProvider;
use Ulams\Courses\Tests\Models\User;

class TestCase extends \Ulams\Core\Tests\TestCase
{
    use CreatesUsers;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Passport::useClientModel(Client::class);
        $this->seed(CourseBuilderPermissionSeeder::class);
        Storage::fake('course_builder_private');
        Storage::fake(config('filesystems.default'));
    }

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            UlamsAuthServiceProvider::class,
            PermissionServiceProvider::class,
            PassportServiceProvider::class,
            CoursesAuthServiceProvider::class,
            UlamsAiServiceProvider::class,
            UlamsCourseBuilderServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
        $app['config']->set('ai.driver', 'fake');
        $app['config']->set('ai.fake.mode', 'synthetic');
        $app['config']->set('ai.fake.cassettes', __DIR__ . '/cassettes');
        $app['config']->set('ai.limits.tenant_monthly_usd', 1000000);
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('course_builder.sse.max_seconds', 0);
        $app['config']->set('course_builder.queue_connection', 'sync');
    }

    protected function fake(): FakeDriver
    {
        /** @var FakeDriver $driver */
        $driver = $this->app->make(LlmDriver::class);

        return $driver;
    }

    protected function author(): User
    {
        return $this->makeInstructor();
    }

    protected function tutor(): User
    {
        return $this->makeInstructor();
    }

    protected function student(): User
    {
        return $this->makeStudent();
    }

    protected function admin(): User
    {
        return $this->makeAdmin();
    }

    protected function fixture(string $name): UploadedFile
    {
        $path = __DIR__ . '/../resources/fixtures/' . $name;
        $copy = tempnam(sys_get_temp_dir(), 'cbfx') . '-' . $name;
        copy($path, $copy);

        return new UploadedFile($copy, $name, null, null, true);
    }

    protected function newSession(User $author): Session
    {
        $id = $this->actingAs($author, 'api')->postJson('/api/admin/course-builder/sessions')->assertCreated()->json('data.session.id');

        return Session::query()->findOrFail($id);
    }

    /** Upload → ingest → interview (runs inline on the sync queue). */
    protected function uploaded(User $author, string $fixture = 'coffee-brewing.md'): Session
    {
        $session = $this->newSession($author);
        $this->actingAs($author, 'api')
            ->post("/api/admin/course-builder/sessions/{$session->id}/sources", ['file' => $this->fixture($fixture)])
            ->assertStatus(202);

        return $session->refresh();
    }

    protected function action(User $author, Session $session, string $name, string $surfaceId, array $context = [])
    {
        return $this->actingAs($author, 'api')->postJson("/api/admin/course-builder/sessions/{$session->id}/runs", [
            'threadId' => $session->id,
            'runId' => 'client-run',
            'messages' => [],
            'forwardedProps' => ['action' => ['name' => $name, 'surfaceId' => $surfaceId, 'sourceComponentId' => 'x', 'context' => $context]],
        ]);
    }

    /** Through the whole interview with "Decide for me" to an outline proposal. */
    protected function toOutline(User $author, string $fixture = 'coffee-brewing.md', array $assessments = ['quiz']): Session
    {
        $session = $this->uploaded($author, $fixture);
        $this->action($author, $session, 'answer', 'interview', ['key' => 'duration', 'value' => ['totalMinutes' => 30, 'lessonMinutes' => 10]])->assertStatus(202);
        $this->action($author, $session, 'answer', 'interview', ['key' => 'assessments', 'value' => $assessments])->assertStatus(202);
        $this->action($author, $session, 'decide_for_me', 'interview')->assertStatus(202);

        return $session->refresh();
    }

    protected function toApplyReview(User $author, string $fixture = 'coffee-brewing.md', array $assessments = ['quiz']): Session
    {
        $session = $this->toOutline($author, $fixture, $assessments);
        $outline = $session->current_version_id;
        $this->action($author, $session, 'approve_outline', "outline-{$outline}", ['versionId' => $outline])->assertStatus(202);

        return $session->refresh();
    }

    protected function toApplied(User $author, string $fixture = 'coffee-brewing.md', array $assessments = ['quiz']): Session
    {
        $session = $this->toApplyReview($author, $fixture, $assessments);
        $version = $session->current_version_id;
        $this->action($author, $session, 'approve_apply', "apply-{$version}", ['versionId' => $version])->assertStatus(202);

        return $session->refresh();
    }
}
