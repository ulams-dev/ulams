<?php

namespace EscolaLms\CourseAccess\Tests;

use EscolaLms\Auth\EscolaLmsAuthServiceProvider;
use EscolaLms\Core\Tests\TestCase as CoreTestCase;
use EscolaLms\Auth\Tests\Models\Client;
use EscolaLms\CourseAccess\EscolaLmsCourseAccessServiceProvider;
use EscolaLms\Courses\EscolaLmsCourseServiceProvider;
use EscolaLms\Courses\Tests\Models\User as UserTest;
use EscolaLms\Scorm\EscolaLmsScormServiceProvider;
use EscolaLms\Tags\EscolaLmsTagsServiceProvider;
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
            EscolaLmsCourseAccessServiceProvider::class,
            EscolaLmsAuthServiceProvider::class,
            EscolaLmsCourseServiceProvider::class,
            PermissionServiceProvider::class,
            PassportServiceProvider::class,
            EscolaLmsScormServiceProvider::class,
            EscolaLmsTagsServiceProvider::class,
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
