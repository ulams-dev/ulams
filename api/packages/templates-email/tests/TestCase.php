<?php

namespace Ulams\TemplatesEmail\Tests;

use Ulams\AssignWithoutAccount\UlamsAssignWithoutAccountServiceProvider;
use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Auth\Models\User;
use Ulams\Auth\Tests\Models\Client;
use Ulams\Cart\UlamsCartServiceProvider;
use Ulams\ConsultationAccess\UlamsConsultationAccessServiceProvider;
use Ulams\Core\Tests\TestCase as CoreTestCase;
use Ulams\CourseAccess\UlamsCourseAccessServiceProvider;
use Ulams\Courses\UlamsCourseServiceProvider;
use Ulams\CsvUsers\UlamsCsvUsersServiceProvider;
use Ulams\Scorm\UlamsScormServiceProvider;
use Ulams\Settings\UlamsSettingsServiceProvider;
use Ulams\Tasks\UlamsTasksServiceProvider;
use Ulams\Templates\UlamsTemplatesServiceProvider;
use Ulams\TemplatesEmail\Database\Seeders\TemplatesEmailSeeder;
use Ulams\TemplatesEmail\UlamsTemplatesEmailServiceProvider;
use Ulams\TemplatesEmail\Services\Contracts\MjmlServiceContract;
use Ulams\TemplatesEmail\Services\MjmlService;
use Ulams\TopicTypeProject\UlamsTopicTypeProjectServiceProvider;
use Ulams\Youtube\UlamsYoutubeServiceProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Laravel\Passport\Passport;
use Laravel\Passport\PassportServiceProvider;
use Mockery;
use Mockery\MockInterface;
use Spatie\Permission\PermissionServiceProvider;

class TestCase extends CoreTestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Passport::useClientModel(Client::class);

        Config::set('ulams_settings.use_database', true);
        Config::set(UlamsTemplatesEmailServiceProvider::CONFIG_KEY . '.mjml.use_api', true);
        $this->instance(
            MjmlServiceContract::class,
            Mockery::mock(MjmlService::class, function (MockInterface $mock) {
                $mock->shouldReceive('render')->andReturnArg(0);
            })
        );
        $this->seed(TemplatesEmailSeeder::class);
    }

    protected function getPackageProviders($app)
    {
        $providers = [
            ...parent::getPackageProviders($app),
            PermissionServiceProvider::class,
            PassportServiceProvider::class,
            UlamsTemplatesServiceProvider::class,
            UlamsTemplatesEmailServiceProvider::class,
        ];
        if (class_exists(UlamsYoutubeServiceProvider::class)) {
            $providers[] = UlamsYoutubeServiceProvider::class;
        }
        if (class_exists(\Ulams\Auth\UlamsAuthServiceProvider::class)) {
            $providers[] = UlamsAuthServiceProvider::class;
        }
        if (class_exists(\Ulams\Courses\UlamsCourseServiceProvider::class)) {
            $providers[] = UlamsCourseServiceProvider::class;
        }
        if (class_exists(\Ulams\CourseAccess\UlamsCourseAccessServiceProvider::class)) {
            $providers[] = UlamsCourseAccessServiceProvider::class;
        }
        if (class_exists(\Ulams\Scorm\UlamsScormServiceProvider::class)) {
            $providers[] = UlamsScormServiceProvider::class;
        }
        if (class_exists(\Ulams\Settings\UlamsSettingsServiceProvider::class)) {
            $providers[] = UlamsSettingsServiceProvider::class;
        }
        if (class_exists(\Ulams\CsvUsers\UlamsCsvUsersServiceProvider::class)) {
            $providers[] = UlamsCsvUsersServiceProvider::class;
        }
        if (class_exists(\Ulams\AssignWithoutAccount\UlamsAssignWithoutAccountServiceProvider::class)) {
            $providers[] = UlamsAssignWithoutAccountServiceProvider::class;
        }
        if (class_exists(\Ulams\Cart\UlamsCartServiceProvider::class)) {
            $providers[] = UlamsCartServiceProvider::class;
        }
        if (class_exists(\Ulams\Tasks\UlamsTasksServiceProvider::class)) {
            $providers[] = UlamsTasksServiceProvider::class;
        }
        if (class_exists(\Ulams\ConsultationAccess\UlamsConsultationAccessServiceProvider::class)) {
            $providers[] = UlamsConsultationAccessServiceProvider::class;
        }
        if (class_exists(\Ulams\TopicTypeProject\UlamsTopicTypeProjectServiceProvider::class)) {
            $providers[] = UlamsTopicTypeProjectServiceProvider::class;
        }
        return $providers;
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
        $app['config']->set(UlamsTemplatesEmailServiceProvider::CONFIG_KEY . '.mjml.use_api', true);
        // Add api keys to local phpunit.xml / testbench.yaml; use github repository secrets in github actions
    }
}
