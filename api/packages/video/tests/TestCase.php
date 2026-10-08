<?php

namespace EscolaLms\Video\Tests;

use EscolaLms\Auth\EscolaLmsAuthServiceProvider;
use EscolaLms\Auth\Models\User;
use EscolaLms\Auth\Tests\Models\Client;
use EscolaLms\Categories\EscolaLmsCategoriesServiceProvider;
use EscolaLms\Courses\EscolaLmsCourseServiceProvider;
use EscolaLms\Scorm\EscolaLmsScormServiceProvider;
use EscolaLms\Settings\EscolaLmsSettingsServiceProvider;
use EscolaLms\Tags\EscolaLmsTagsServiceProvider;
use EscolaLms\TopicTypes\EscolaLmsTopicTypesServiceProvider;
use EscolaLms\Video\EscolaLmsVideoServiceProvider;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Passport\Passport;
use Laravel\Passport\PassportServiceProvider;
use ProtoneMedia\LaravelFFMpeg\Support\ServiceProvider as FFMpegServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

class TestCase extends \EscolaLms\Core\Tests\TestCase
{
    use DatabaseTransactions;

    protected $response;

    protected function setUp(): void
    {
        parent::setUp();
        Passport::useClientModel(Client::class);
    }

    protected function getPackageProviders($app)
    {
        return [
            ...parent::getPackageProviders($app),
            EscolaLmsAuthServiceProvider::class,
            PermissionServiceProvider::class,
            PassportServiceProvider::class,
            EscolaLmsScormServiceProvider::class,
            EscolaLmsCategoriesServiceProvider::class,
            EscolaLmsCourseServiceProvider::class,
            EscolaLmsTopicTypesServiceProvider::class,
            EscolaLmsTagsServiceProvider::class,
            EscolaLmsSettingsServiceProvider::class,
            EscolaLmsVideoServiceProvider::class,
            FFMpegServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // make sure, our .env file is loaded
        $app->useEnvironmentPath(__DIR__ . '/..');
        $app->bootstrapWith([LoadEnvironmentVariables::class]);

        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
        $app['config']->set('database.connections.mysql.strict', false);
        $app['config']->set('app.debug', (bool)env('APP_DEBUG', true));

        // need to have aws auth config in .env
        $app['config']->set('filesystems.disks.s3.key', env('AWS_ACCESS_KEY_ID'));
        $app['config']->set('filesystems.disks.s3.secret', env('AWS_SECRET_ACCESS_KEY'));
        $app['config']->set('filesystems.disks.s3.region', env('AWS_DEFAULT_REGION'));
        $app['config']->set('filesystems.disks.s3.bucket', env('AWS_BUCKET'));
    }
}
