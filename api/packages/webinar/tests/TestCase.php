<?php

namespace Ulams\Webinar\Tests;

use Ulams\Webinar\Providers\EventServiceProvider;
use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Auth\Models\User;
use Ulams\Auth\Tests\Models\Client;
use Ulams\Webinar\AuthServiceProvider;
use Ulams\Webinar\UlamsWebinarServiceProvider;
use Ulams\Youtube\UlamsYoutubeServiceProvider;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

class TestCase extends \Ulams\Core\Tests\TestCase
{
    protected ?TestResponse $response;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.youtube.client_id' => 'test_client_id',
            'services.youtube.client_secret' => 'test_secret',
            'services.youtube.api_key' => 'test_api_key',
            'services.youtube.refresh_token' => 'test_refresh_token',
            'services.youtube.redirect_url' => 'redirect_url',
            // firebase/php-jwt 7 rejects HS256 keys shorter than 256 bits (CVE-2025-45769)
            'jitsi.secret' => str_repeat('s', 32),
        ]);

        Passport::useClientModel(Client::class);
    }

    protected function getPackageProviders($app)
    {
        return [
            ...parent::getPackageProviders($app),
            UlamsAuthServiceProvider::class,
            UlamsWebinarServiceProvider::class,
            PermissionServiceProvider::class,
            PassportServiceProvider::class,
            AuthServiceProvider::class,
            UlamsYoutubeServiceProvider::class,
            EventServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
        $app['config']->set('database.connections.mysql.strict', false);
        $app['config']->set('app.debug', (bool) env('APP_DEBUG', true));
    }

    public function assertApiResponse(array $actualData)
    {
        $this->assertApiSuccess();

        $response = json_decode($this->response->getContent(), true);
        $responseData = $response['data'];

        $this->assertNotEmpty($responseData['id']);
        $this->assertModelData($actualData, $responseData);
    }

    public function assertApiSuccess()
    {
        $this->response->assertJson(['success' => true]);
    }

    public function assertModelData(array $actualData, array $expectedData)
    {
        foreach ($actualData as $key => $value) {
            if (in_array($key, ['created_at', 'updated_at'])) {
                continue;
            }
            $this->assertEquals($actualData[$key], $expectedData[$key]);
        }
    }
}
