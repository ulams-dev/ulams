<?php

namespace EscolaLms\Recommender\Tests;

use EscolaLms\Auth\EscolaLmsAuthServiceProvider;
use EscolaLms\Auth\Models\User;
use EscolaLms\Courses\EscolaLmsCourseServiceProvider;
use EscolaLms\HeadlessH5P\HeadlessH5PServiceProvider;
use EscolaLms\Recommender\EscolaLmsRecommenderServiceProvider;
use EscolaLms\Core\Tests\TestCase as CoreTestCase;
use EscolaLms\TopicTypes\EscolaLmsTopicTypesServiceProvider;
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
            EscolaLmsAuthServiceProvider::class,
            EscolaLmsCourseServiceProvider::class,
            EscolaLmsTopicTypesServiceProvider::class,
            HeadlessH5PServiceProvider::class,
            EscolaLmsRecommenderServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
    }
}
