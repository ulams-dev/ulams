<?php

namespace Ulams\CourseAccess\Tests;

use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Core\Tests\TestCase as CoreTestCase;
use Ulams\Auth\Tests\Models\Client;
use Ulams\CourseAccess\UlamsCourseAccessServiceProvider;
use Ulams\Courses\UlamsCourseServiceProvider;
use Ulams\Courses\Tests\Models\User as UserTest;
use Ulams\Scorm\UlamsScormServiceProvider;
use Ulams\Tags\UlamsTagsServiceProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

class TestCase extends CoreTestCase
{
    use DatabaseTransactions, WithFaker;

    protected ?TestResponse $response;

    protected function setUp(): void
    {
        parent::setUp();
        Passport::useClientModel(Client::class);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            UlamsCourseAccessServiceProvider::class,
            UlamsAuthServiceProvider::class,
            UlamsCourseServiceProvider::class,
            PermissionServiceProvider::class,
            PassportServiceProvider::class,
            UlamsScormServiceProvider::class,
            UlamsTagsServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', UserTest::class);
        $app['config']->set('passport.client_uuids', true);
        $app['config']->set('database.connections.mysql.strict', false);
        $app['config']->set('app.debug', (bool)env('APP_DEBUG', true));
    }
}
