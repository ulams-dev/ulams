<?php

namespace Ulams\TemplatesPdf\Tests;

use Ulams\Categories\UlamsCategoriesServiceProvider;
use Ulams\Core\Models\User;
use Ulams\TemplatesPdf\UlamsTemplatesPdfServiceProvider;
use Ulams\Templates\Database\Seeders\PermissionTableSeeder as TemplatesPermissionTableSeeder;
use Ulams\TemplatesPdf\Database\Seeders\PermissionTableSeeder as TemplatesPdfPermissionTableSeeder;

use Ulams\Templates\UlamsTemplatesServiceProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Ulams\Courses\UlamsCourseServiceProvider;

class TestCase extends \Ulams\Core\Tests\TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TemplatesPermissionTableSeeder::class);
        $this->seed(TemplatesPdfPermissionTableSeeder::class);
    }

    protected function getPackageProviders($app): array
    {
        $providers = [
            ...parent::getPackageProviders($app),
            PassportServiceProvider::class,
            PermissionServiceProvider::class,
            UlamsCourseServiceProvider::class,
            UlamsTemplatesServiceProvider::class,
            UlamsCategoriesServiceProvider::class,
            UlamsTemplatesPdfServiceProvider::class,
        ];

        if (class_exists(\Ulams\Auth\UlamsAuthServiceProvider::class)) {
            $providers[] = \Ulams\Auth\UlamsAuthServiceProvider::class;
        }

        return $providers;
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
    }
}
