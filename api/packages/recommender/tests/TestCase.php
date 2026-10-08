<?php

namespace Ulams\Recommender\Tests;

use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Auth\Models\User;
use Ulams\Courses\UlamsCourseServiceProvider;
use Ulams\HeadlessH5P\HeadlessH5PServiceProvider;
use Ulams\Recommender\UlamsRecommenderServiceProvider;
use Ulams\Core\Tests\TestCase as CoreTestCase;
use Ulams\TopicTypes\UlamsTopicTypesServiceProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

class TestCase extends CoreTestCase
{
    use DatabaseTransactions;

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            PassportServiceProvider::class,
            PermissionServiceProvider::class,
            UlamsAuthServiceProvider::class,
            UlamsCourseServiceProvider::class,
            UlamsTopicTypesServiceProvider::class,
            HeadlessH5PServiceProvider::class,
            UlamsRecommenderServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
    }
}
