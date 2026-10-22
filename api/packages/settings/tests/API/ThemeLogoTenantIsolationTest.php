<?php

namespace Ulams\Settings\Tests\API;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Bridge\AccessTokenRepository;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResourceServer;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Settings\Database\Seeders\DatabaseSeeder;
use Ulams\Settings\Database\Seeders\PermissionTableSeeder;
use Ulams\Settings\Models\Setting;
use Ulams\Settings\Tests\TestCase;

/**
 * Tenants have separate databases and their own Passport key pair (ADR 0007), so a token issued by one tenant
 * must be rejected by another on the logo endpoints and the theme settings. Same technique as
 * packages/templates-email/tests/Api/TenantIsolationTest.php.
 */
class ThemeLogoTenantIsolationTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    public function testATokenOfAnotherTenantIsRejectedOnTheLogoAndThemeEndpoints(): void
    {
        $this->seed(PermissionTableSeeder::class);
        $this->seed(DatabaseSeeder::class);
        Storage::fake();
        $token = $this->makeAdmin()->createToken('test')->accessToken;
        $png = fn () => ['logo' => UploadedFile::fake()->createWithContent('a.png', base64_decode(self::PNG))];
        $theme = ['group' => 'theme', 'key' => 'theme', 'type' => 'text', 'value' => 'poland', 'public' => true, 'enumerable' => true];

        $this->asToken($token, 'POST', '/api/admin/theme/logo', $png())->assertOk();
        $this->asToken($token, 'POST', '/api/admin/settings', $theme)->assertOk();
        $logo = Setting::query()->where(['group' => 'theme', 'key' => 'logo'])->value('value');
        $this->assertCount(1, Storage::disk()->allFiles('theme'));

        $this->switchToTenantWithOwnKeys();

        $this->asToken($token, 'POST', '/api/admin/theme/logo', $png())->assertUnauthorized();
        $this->asToken($token, 'DELETE', '/api/admin/theme/logo')->assertUnauthorized();
        $this->asToken($token, 'POST', '/api/admin/settings', ['value' => 'coffee'] + $theme)->assertUnauthorized();

        // the foreign token stored nothing and removed nothing
        $this->assertSame([$logo], Storage::disk()->allFiles('theme'));
        $this->assertSame($logo, Setting::query()->where(['group' => 'theme', 'key' => 'logo'])->value('value'));
        $this->assertSame('poland', Setting::query()->where(['group' => 'theme', 'key' => 'theme'])->value('value'));
    }

    private function asToken(string $token, string $method, string $url, array $body = [])
    {
        $headers = ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'];
        $response = $method === 'POST' && isset($body['logo'])
            ? $this->post($url, $body, $headers)
            : $this->json($method, $url, $body, $headers);
        Auth::forgetGuards();

        return $response;
    }

    private function switchToTenantWithOwnKeys(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        $public = openssl_pkey_get_details($key)['key'];

        config(['passport.private_key' => $private, 'passport.public_key' => $public]);
        foreach ([AuthorizationServer::class, ResourceServer::class, AccessTokenRepository::class] as $service) {
            $this->app->forgetInstance($service);
        }
        Auth::forgetGuards();
    }
}
