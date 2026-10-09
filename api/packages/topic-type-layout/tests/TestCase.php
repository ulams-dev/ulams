<?php

namespace Ulams\TopicTypeLayout\Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Passport\Passport;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Ulams\Auth\Models\User;
use Ulams\Auth\Tests\Models\Client;
use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Core\Tests\TestCase as CoreTestCase;
use Ulams\TopicTypeLayout\UlamsTopicTypeLayoutServiceProvider;

class TestCase extends CoreTestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Passport::useClientModel(Client::class);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            PassportServiceProvider::class,
            PermissionServiceProvider::class,
            UlamsAuthServiceProvider::class,
            UlamsTopicTypeLayoutServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
    }

    /**
     * Example props of one approved component (copied from front/ui/catalogue/examples).
     *
     * @return array{props: array<string, mixed>, invalid: array<string, mixed>}
     */
    protected function example(string $component): array
    {
        $all = json_decode((string) file_get_contents(__DIR__ . '/Fixtures/examples.json'), true);

        return $all[$component];
    }

    /** @return string[] */
    protected function exampleNames(): array
    {
        return array_keys(json_decode((string) file_get_contents(__DIR__ . '/Fixtures/examples.json'), true));
    }
}
