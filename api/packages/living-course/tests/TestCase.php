<?php

namespace Ulams\LivingCourse\Tests;

use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Source;
use Ulams\CourseBuilder\Tests\TestCase as BuilderTestCase;
use Ulams\Courses\Tests\Models\User;
use Ulams\LivingCourse\Database\Seeders\LivingCoursePermissionSeeder;
use Ulams\LivingCourse\Enums\LivingCoursePermissionsEnum;
use Ulams\LivingCourse\Models\Connection;
use Ulams\LivingCourse\UlamsLivingCourseServiceProvider;

/** The Course Builder test case (fake driver, cassettes, users) with Living Course loaded. */
class TestCase extends BuilderTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LivingCoursePermissionSeeder::class);
    }

    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), UlamsLivingCourseServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('living_course.webhook_debounce_seconds', 0);
    }

    /** Fixture of this package (resources/fixtures), as an upload. */
    protected function lcFixture(string $name): UploadedFile
    {
        $path = __DIR__ . '/../resources/fixtures/' . $name;
        $copy = tempnam(sys_get_temp_dir(), 'lcfx') . '-' . $name;
        copy($path, $copy);

        return new UploadedFile($copy, $name, null, null, true);
    }

    /** An admin who may not decide proposals (no living_course_review). */
    protected function readOnlyAdmin(): User
    {
        $admin = $this->admin();
        Role::findByName('admin', 'api')->revokePermissionTo(LivingCoursePermissionsEnum::LIVING_COURSE_REVIEW);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $admin;
    }

    /** A builder session with its source ingested (revision 1 exists). */
    protected function sessionWithSource(User $author, string $fixture = 'coffee-brewing.md'): Session
    {
        return $this->uploaded($author, $fixture);
    }

    protected function sourceOf(Session $session): Source
    {
        return $session->sources()->firstOrFail();
    }

    protected function connectionOf(Session $session): Connection
    {
        return Connection::query()->where('session_id', $session->id)->firstOrFail();
    }
}
