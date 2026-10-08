<?php

namespace Ulams\Core\Tests\Mocks\ExampleEntity;

use Ulams\Core\UlamsServiceProvider;

class TestCase extends \Ulams\Core\Tests\TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            ...parent::getPackageProviders($app),
            ExampleEntityServiceProvider::class
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        ExampleEntityMigration::run();
    }
}
