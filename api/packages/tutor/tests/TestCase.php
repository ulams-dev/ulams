<?php

namespace Ulams\Tutor\Tests;

use Ulams\CourseBuilder\Tests\TestCase as BuilderTestCase;
use Ulams\LearnerInsights\Database\Seeders\LearnerInsightsPermissionSeeder;
use Ulams\LearnerInsights\UlamsLearnerInsightsServiceProvider;
use Ulams\Tutor\UlamsTutorServiceProvider;

/** The Course Builder test case (fake driver, users, a real builder pipeline) with the tutor on. */
class TestCase extends BuilderTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LearnerInsightsPermissionSeeder::class);
    }

    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), UlamsLearnerInsightsServiceProvider::class, UlamsTutorServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('tutor.enabled', true);
        $app['config']->set('learner_insights.enabled', true);
        $app['config']->set('cache.default', 'array');
    }
}
