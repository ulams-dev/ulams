<?php

namespace Tests\Integrations;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Passport 13's device authorization grant is disabled (AppServiceProvider): the API issues
 * personal access tokens only.
 */
class PassportDeviceRoutesTest extends TestCase
{
    public function testDeviceRoutesAreNotRegistered(): void
    {
        foreach (['passport.device', 'passport.device.code', 'passport.device.authorizations.authorize', 'passport.device.authorizations.approve', 'passport.device.authorizations.deny'] as $name) {
            $this->assertFalse(Route::has($name), $name);
        }
    }

    public function testDeviceEndpointsAnswer404(): void
    {
        $this->get('/oauth/device')->assertNotFound();
        $this->postJson('/oauth/device/code', ['client_id' => 'x'])->assertNotFound();
        $this->get('/oauth/device/authorize')->assertNotFound();
        $this->postJson('/oauth/device/authorize')->assertNotFound();
        $this->deleteJson('/oauth/device/authorize')->assertNotFound();
    }

    public function testTokenEndpointStillExists(): void
    {
        $this->assertTrue(Route::has('passport.token'));
    }
}
