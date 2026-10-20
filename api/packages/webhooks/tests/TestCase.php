<?php

namespace Ulams\Webhooks\Tests;

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Passport\Passport;
use Laravel\Passport\PassportServiceProvider;
use Psr\Http\Message\RequestInterface;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionServiceProvider;
use Ulams\Auth\Models\User;
use Ulams\Auth\Tests\Models\Client;
use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Core\Enums\UserRole;
use Ulams\Core\Http\SafeHttp;
use Ulams\Core\Tests\TestCase as CoreTestCase;
use Ulams\Webhooks\Database\Seeders\WebhookPermissionSeeder;
use Ulams\Webhooks\Models\WebhookEndpoint;
use Ulams\Webhooks\Services\EndpointService;
use Ulams\Webhooks\UlamsWebhooksServiceProvider;

class TestCase extends CoreTestCase
{
    use DatabaseTransactions;

    /** @var list<RequestInterface> requests the fake receiver got */
    protected array $received = [];

    protected function setUp(): void
    {
        parent::setUp();
        Passport::useClientModel(Client::class);
        Role::findOrCreate(UserRole::STUDENT, 'api');
        $this->seed(WebhookPermissionSeeder::class);
        // a public address for every host name, so no test needs the network
        SafeHttp::useResolver(fn (string $host) => ['93.184.216.34']);
    }

    protected function tearDown(): void
    {
        SafeHttp::useResolver(null);
        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            PassportServiceProvider::class,
            PermissionServiceProvider::class,
            UlamsAuthServiceProvider::class,
            UlamsWebhooksServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
        $app['config']->set('cache.default', 'array');
        // retries are scheduled by the sweeper under the synchronous queue; the tests drive them directly
        $app['config']->set('queue.default', 'sync');
    }

    /**
     * Fake receiver: each item is an HTTP status, a Response, or an exception, used in order (200 once they run out).
     * Requests are collected in $this->received. A plain handler, not Guzzle's MockHandler: that one hands the queue
     * item itself to `on_headers`, which the size guard of SafeHttp sets.
     *
     * @param array<int,int|Response|\Throwable> $answers
     */
    protected function receiver(array $answers): void
    {
        $this->app->instance('webhooks.http_handler', function (RequestInterface $request, array $options) use (&$answers) {
            $this->received[] = $request;
            $answer = array_shift($answers) ?? 200;
            if ($answer instanceof \Throwable) {
                return Create::rejectionFor($answer);
            }
            $response = $answer instanceof Response ? $answer : new Response((int) $answer, [], 'ok');
            if (isset($options['on_headers'])) {
                ($options['on_headers'])($response);
            }

            return Create::promiseFor($response);
        });
    }

    /** @return array{0:WebhookEndpoint,1:string} */
    protected function endpoint(array $data = []): array
    {
        return app(EndpointService::class)->create($data + ['url' => 'https://hooks.example.com/ulams', 'events' => ['*']], null);
    }
}
