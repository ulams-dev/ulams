<?php

namespace Ulams\CourseVariants\Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Passport\Passport;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Ulams\Auth\Tests\Models\Client;
use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\AuthServiceProvider as CoursesAuthServiceProvider;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Tests\Models\User;
use Ulams\CourseVariants\UlamsCourseVariantsServiceProvider;

class TestCase extends \Ulams\Core\Tests\TestCase
{
    use CreatesUsers;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Passport::useClientModel(Client::class);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            UlamsAuthServiceProvider::class,
            PermissionServiceProvider::class,
            PassportServiceProvider::class,
            CoursesAuthServiceProvider::class,
            UlamsCourseVariantsServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
        $app['config']->set('cache.default', 'array');
    }

    protected function publishedCourse(string $title): Course
    {
        return Course::factory()->create([
            'status' => CourseStatusEnum::PUBLISHED, 'active_from' => null, 'active_to' => null,
            'title' => $title, 'subtitle' => '', 'description' => '', 'findable' => true,
        ]);
    }
}
