<?php

namespace Ulams\TemplatesSms\Tests;

use Ulams\Consultations\UlamsConsultationsServiceProvider;
use Ulams\Core\Models\User;
use Ulams\Settings\UlamsSettingsServiceProvider;
use Ulams\TemplatesSms\UlamsTemplatesSmsServiceProvider;
use Ulams\Templates\Database\Seeders\PermissionTableSeeder as TemplatesPermissionTableSeeder;
use Ulams\Templates\UlamsTemplatesServiceProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Tzsk\Sms\SmsServiceProvider;

class TestCase extends \Ulams\Core\Tests\TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TemplatesPermissionTableSeeder::class);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            PassportServiceProvider::class,
            PermissionServiceProvider::class,
            UlamsTemplatesServiceProvider::class,
            UlamsSettingsServiceProvider::class,
            UlamsConsultationsServiceProvider::class,
            UlamsTemplatesSmsServiceProvider::class,
            SmsServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
    }
}
