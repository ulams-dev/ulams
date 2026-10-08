<?php

namespace EscolaLms\TemplatesEmail\Tests;

use EscolaLms\AssignWithoutAccount\EscolaLmsAssignWithoutAccountServiceProvider;
use EscolaLms\Auth\EscolaLmsAuthServiceProvider;
use EscolaLms\Auth\Models\User;
use EscolaLms\Auth\Tests\Models\Client;
use EscolaLms\Cart\EscolaLmsCartServiceProvider;
use EscolaLms\ConsultationAccess\EscolaLmsConsultationAccessServiceProvider;
use EscolaLms\Core\Tests\TestCase as CoreTestCase;
use EscolaLms\CourseAccess\EscolaLmsCourseAccessServiceProvider;
use EscolaLms\Courses\EscolaLmsCourseServiceProvider;
use EscolaLms\CsvUsers\EscolaLmsCsvUsersServiceProvider;
use EscolaLms\Scorm\EscolaLmsScormServiceProvider;
use EscolaLms\Settings\EscolaLmsSettingsServiceProvider;
use EscolaLms\Tasks\EscolaLmsTasksServiceProvider;
use EscolaLms\Templates\EscolaLmsTemplatesServiceProvider;
use EscolaLms\TemplatesEmail\Database\Seeders\TemplatesEmailSeeder;
use EscolaLms\TemplatesEmail\EscolaLmsTemplatesEmailServiceProvider;
use EscolaLms\TemplatesEmail\Services\Contracts\MjmlServiceContract;
use EscolaLms\TemplatesEmail\Services\MjmlService;
use EscolaLms\TopicTypeProject\EscolaLmsTopicTypeProjectServiceProvider;
use EscolaLms\Youtube\EscolaLmsYoutubeServiceProvider;
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

        Config::set('escola_settings.use_database', true);
        Config::set(EscolaLmsTemplatesEmailServiceProvider::CONFIG_KEY . '.mjml.use_api', true);
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
            EscolaLmsTemplatesServiceProvider::class,
            EscolaLmsTemplatesEmailServiceProvider::class,
        ];
        if (class_exists(EscolaLmsYoutubeServiceProvider::class)) {
            $providers[] = EscolaLmsYoutubeServiceProvider::class;
        }
        if (class_exists(\EscolaLms\Auth\EscolaLmsAuthServiceProvider::class)) {
            $providers[] = EscolaLmsAuthServiceProvider::class;
        }
        if (class_exists(\EscolaLms\Courses\EscolaLmsCourseServiceProvider::class)) {
            $providers[] = EscolaLmsCourseServiceProvider::class;
        }
        if (class_exists(\EscolaLms\CourseAccess\EscolaLmsCourseAccessServiceProvider::class)) {
            $providers[] = EscolaLmsCourseAccessServiceProvider::class;
        }
        if (class_exists(\EscolaLms\Scorm\EscolaLmsScormServiceProvider::class)) {
            $providers[] = EscolaLmsScormServiceProvider::class;
        }
        if (class_exists(\EscolaLms\Settings\EscolaLmsSettingsServiceProvider::class)) {
            $providers[] = EscolaLmsSettingsServiceProvider::class;
        }
        if (class_exists(\EscolaLms\CsvUsers\EscolaLmsCsvUsersServiceProvider::class)) {
            $providers[] = EscolaLmsCsvUsersServiceProvider::class;
        }
        if (class_exists(\EscolaLms\AssignWithoutAccount\EscolaLmsAssignWithoutAccountServiceProvider::class)) {
            $providers[] = EscolaLmsAssignWithoutAccountServiceProvider::class;
        }
        if (class_exists(\EscolaLms\Cart\EscolaLmsCartServiceProvider::class)) {
            $providers[] = EscolaLmsCartServiceProvider::class;
        }
        if (class_exists(\EscolaLms\Tasks\EscolaLmsTasksServiceProvider::class)) {
            $providers[] = EscolaLmsTasksServiceProvider::class;
        }
        if (class_exists(\EscolaLms\ConsultationAccess\EscolaLmsConsultationAccessServiceProvider::class)) {
            $providers[] = EscolaLmsConsultationAccessServiceProvider::class;
        }
        if (class_exists(\EscolaLms\TopicTypeProject\EscolaLmsTopicTypeProjectServiceProvider::class)) {
            $providers[] = EscolaLmsTopicTypeProjectServiceProvider::class;
        }
        return $providers;
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
        $app['config']->set(EscolaLmsTemplatesEmailServiceProvider::CONFIG_KEY . '.mjml.use_api', true);
        // Add api keys to local phpunit.xml / testbench.yaml; use github repository secrets in github actions
    }
}
