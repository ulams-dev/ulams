<?php

namespace Ulams\Settings\Tests\API;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Ulams\Core\Tests\Concerns\ForeignTenantToken;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Settings\Database\Seeders\DatabaseSeeder;
use Ulams\Settings\Database\Seeders\PermissionTableSeeder;
use Ulams\Settings\Models\Setting;
use Ulams\Settings\Tests\TestCase;

/**
 * Tenants have separate databases and their own Passport key pair (ADR 0007), so a token issued by one tenant
 * must be rejected by another on the logo endpoints and the theme settings (ForeignTenantToken, ADR 0190). The route
 * walk of tests/Integrations/TenantIsolationCoverageTest covers the bare 401; this checks that nothing is stored or removed.
 */
class ThemeLogoTenantIsolationTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers, ForeignTenantToken;

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
}
