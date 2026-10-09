<?php

namespace Ulams\Demo\Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Passport\Passport;
use Ulams\Auth\Database\Seeders\AuthPermissionSeeder;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Ulams\Auth\Models\User;
use Ulams\Auth\Tests\Models\Client;
use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Core\Tests\TestCase as CoreTestCase;
use Ulams\CourseAccess\UlamsCourseAccessServiceProvider;
use Ulams\Courses\UlamsCourseServiceProvider;
use Ulams\Demo\UlamsDemoServiceProvider;
use Ulams\Scorm\UlamsScormServiceProvider;
use Ulams\Settings\UlamsSettingsServiceProvider;
use Ulams\Tags\UlamsTagsServiceProvider;
use Ulams\Tenancy\UlamsTenancyServiceProvider;

/**
 * Demo mode is on (DEMO_MODE=true) unless a test case overrides `demoModeEnabled()`. The
 * demo accounts are pinned by e-mail, because the shared test database may hold other
 * admins and students.
 */
class TestCase extends CoreTestCase
{
    use DatabaseTransactions;

    public const ADMIN_EMAIL = 'admin@demo-test.ulams.app';
    public const STUDENT_EMAIL = 'student1@demo-test.ulams.app';

    protected function setUp(): void
    {
        parent::setUp();
        Passport::useClientModel(Client::class);
        // roles with the permissions profile endpoints check
        $this->seed(AuthPermissionSeeder::class);
    }

    protected function demoModeEnabled(): bool
    {
        return true;
    }

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            PassportServiceProvider::class,
            PermissionServiceProvider::class,
            UlamsAuthServiceProvider::class,
            UlamsSettingsServiceProvider::class,
            UlamsCourseServiceProvider::class,
            UlamsCourseAccessServiceProvider::class,
            UlamsScormServiceProvider::class,
            UlamsTagsServiceProvider::class,
            UlamsTenancyServiceProvider::class,
            UlamsDemoServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
        $app['config']->set('ulams_tenancy.tenant_slug', null);
        $app['config']->set('ulams_demo.enabled', $this->demoModeEnabled());
        $app['config']->set('ulams_demo.admin_email', self::ADMIN_EMAIL);
        $app['config']->set('ulams_demo.student_email', self::STUDENT_EMAIL);
        $app['config']->set('ulams_demo.admin_url', 'http://demotest.admin.localhost');
        $app['config']->set('ulams_demo.front_url', 'http://demotest.app.localhost');
    }

    protected function seedDemoUsers(): array
    {
        $admin = User::factory()->create(['email' => self::ADMIN_EMAIL, 'is_active' => true]);
        $admin->assignRole('admin');
        $student = User::factory()->create(['email' => self::STUDENT_EMAIL, 'is_active' => true]);
        $student->assignRole('student');

        return [$admin, $student];
    }
}
