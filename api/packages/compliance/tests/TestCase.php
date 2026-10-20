<?php

namespace Ulams\Compliance\Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Ulams\Categories\UlamsCategoriesServiceProvider;
use Ulams\Compliance\Database\Seeders\CompliancePermissionSeeder;
use Ulams\Compliance\UlamsComplianceServiceProvider;
use Ulams\Courses\Models\User;
use Ulams\Courses\UlamsCourseServiceProvider;
use Ulams\Templates\Database\Seeders\PermissionTableSeeder as TemplatesPermissionTableSeeder;
use Ulams\Templates\UlamsTemplatesServiceProvider;
use Ulams\TemplatesPdf\Database\Seeders\PermissionTableSeeder as TemplatesPdfPermissionTableSeeder;
use Ulams\TemplatesPdf\UlamsTemplatesPdfServiceProvider;

class TestCase extends \Ulams\Core\Tests\TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->seed(TemplatesPermissionTableSeeder::class);
        $this->seed(TemplatesPdfPermissionTableSeeder::class);
        $this->seed(CompliancePermissionSeeder::class);
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
            UlamsComplianceServiceProvider::class,
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
        $app['config']->set('ulams_templates_pdf.storage.render_on_create', false);
    }
}
