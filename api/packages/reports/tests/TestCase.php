<?php

namespace Ulams\Reports\Tests;

use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Cart\UlamsCartServiceProvider;
use Ulams\Cart\Facades\Shop;
use Ulams\Categories\UlamsCategoriesServiceProvider;
use Ulams\Core\Tests\TestCase as CoreTestCase;
use Ulams\Courses\UlamsCourseServiceProvider;
use Ulams\H5P\UlamsH5PServiceProvider;
use Ulams\Payments\Providers\PaymentsServiceProvider;
use Ulams\Questionnaire\UlamsQuestionnaireServiceProvider;
use Ulams\Reports\Database\Seeders\ReportsPermissionSeeder;
use Ulams\Reports\UlamsReportsServiceProvider;
use Ulams\Reports\Tests\Models\Client;
use Ulams\Reports\Tests\Models\Course;
use Ulams\Reports\Tests\Models\TestUser;
use Ulams\Scorm\UlamsScormServiceProvider;
use Ulams\TopicTypes\UlamsTopicTypesServiceProvider;
use Ulams\TopicTypeGift\UlamsTopicTypeGiftServiceProvider;
use Laravel\Passport\Passport;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

class TestCase extends CoreTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Passport::useClientModel(Client::class);
        $this->seed(ReportsPermissionSeeder::class);

        Shop::registerProductableClass(Course::class);
    }

    protected function getPackageProviders($app)
    {
        return [
            ...parent::getPackageProviders($app),
            UlamsAuthServiceProvider::class,
            PermissionServiceProvider::class,
            PassportServiceProvider::class,
            UlamsCategoriesServiceProvider::class,
            UlamsReportsServiceProvider::class,
            UlamsCourseServiceProvider::class,
            UlamsTopicTypesServiceProvider::class,
            UlamsH5PServiceProvider::class,
            PaymentsServiceProvider::class,
            UlamsCartServiceProvider::class,
            UlamsScormServiceProvider::class,
            UlamsQuestionnaireServiceProvider::class,
            UlamsTopicTypeGiftServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', TestUser::class);
        $app['config']->set('passport.client_uuids', true);
    }
}
