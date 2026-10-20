<?php

namespace Ulams\Questionnaire\Tests;

use Ulams\Core\Support\MorphMap;
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

    protected function setUp(): void
    {
        parent::setUp();
        // the models the questionnaires and reports refer to store their morph aliases
        foreach ([
            \Ulams\Courses\UlamsCourseServiceProvider::MORPH_ALIASES,
            \Ulams\Consultations\UlamsConsultationsServiceProvider::MORPH_ALIASES,
            \Ulams\Webinar\UlamsWebinarServiceProvider::MORPH_ALIASES,
            \Ulams\StationaryEvents\UlamsStationaryEventsServiceProvider::MORPH_ALIASES,
        ] as $aliases) {
            MorphMap::register($aliases);
        }
    }

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
