<?php

namespace Ulams\Mattermost\Tests;

use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\CourseAccess\UlamsCourseAccessServiceProvider;
use Ulams\Courses\UlamsCourseServiceProvider;
use Ulams\Mattermost\Providers\SettingsServiceProvider;
use Ulams\Scorm\UlamsScormServiceProvider;
use Ulams\Settings\Facades\AdministrableConfig;
use Ulams\Tags\UlamsTagsServiceProvider;
use Ulams\Webinar\UlamsWebinarServiceProvider;
use GuzzleHttp\Middleware;
use Ulams\Mattermost\UlamsMattermostServiceProvider;
use Ulams\Settings\UlamsSettingsServiceProvider;
use Gnello\Mattermost\Laravel\MattermostServiceProvider;
use Illuminate\Support\Facades\Config;
use Laravel\Passport\Passport;
use Ulams\Lrs\Tests\Models\Client;
use Ulams\Auth\Models\User;
use Ulams\Core\Tests\TestCase as CoreTestCase;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class TestCase extends CoreTestCase
{
    protected MockHandler $mock;
    protected $history;
    protected $container = [];

    protected function setUp(): void
    {
        parent::setUp();
        Passport::useClientModel(Client::class);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            UlamsMattermostServiceProvider::class,
            MattermostServiceProvider::class,
            UlamsAuthServiceProvider::class,
            UlamsCourseServiceProvider::class,
            UlamsScormServiceProvider::class,
            UlamsSettingsServiceProvider::class,
            UlamsTagsServiceProvider::class,
            UlamsWebinarServiceProvider::class,
            UlamsCourseAccessServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);

        $this->mock = new MockHandler([new Response(200, ['Token' => 'Token'], 'Hello, World'),]);
        $this->history = Middleware::history($this->container);
        $handlerStack = HandlerStack::create($this->mock);
        $handlerStack->push($this->history);

        $app['config']->set('mattermost.servers.default.guzzle', ['handler' => $handlerStack]);
    }

    public function setPackageStatus($packageStatus): void
    {
        Config::set(SettingsServiceProvider::CONFIG_KEY . '.package_status', $packageStatus);
        Config::set('ulams_settings.use_database', true);
        AdministrableConfig::storeConfig();
        $this->refreshApplication();
    }
}
