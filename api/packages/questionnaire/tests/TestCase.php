<?php

namespace Ulams\Questionnaire\Tests;

use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Categories\UlamsCategoriesServiceProvider;
use Ulams\Core\Models\User;
use Ulams\Courses\UlamsCourseServiceProvider;
use Ulams\Questionnaire\UlamsQuestionnaireServiceProvider;
use Ulams\Scorm\UlamsScormServiceProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class TestCase extends \Ulams\Core\Tests\TestCase
{
    use DatabaseTransactions;

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            UlamsQuestionnaireServiceProvider::class,
            UlamsCourseServiceProvider::class,
            UlamsCategoriesServiceProvider::class,
            UlamsScormServiceProvider::class,
            UlamsAuthServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
    }

    protected function authenticateAsAdmin(): void
    {
        $this->user = config('auth.providers.users.model')::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole('admin');
    }
}
