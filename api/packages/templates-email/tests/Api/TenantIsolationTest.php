<?php

namespace Ulams\TemplatesEmail\Tests\Api;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Bridge\AccessTokenRepository;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResourceServer;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Templates\Database\Seeders\PermissionTableSeeder;
use Ulams\Templates\Events\ManuallyTriggeredEvent;
use Ulams\Templates\Models\Template;
use Ulams\TemplatesEmail\Core\EmailChannel;
use Ulams\TemplatesEmail\Tests\TestCase;

/**
 * Tenants have separate databases and their own Passport key pair (ADR 0007), so a token issued by one
 * tenant must be rejected by another on both draft endpoints. Same technique as
 * packages/compliance/tests/Api/TenantIsolationTest.php.
 */
class TenantIsolationTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    public function testATokenOfAnotherTenantIsRejectedOnTheDraftEndpoints(): void
    {
        $this->seed(PermissionTableSeeder::class);
        Mail::fake();
        Storage::fake();
        $token = $this->makeAdmin()->createToken('test')->accessToken;
        $body = ['event' => ManuallyTriggeredEvent::class, 'title' => 'Hi @VarUserName', 'content' => '<p>@VarUserName</p>'];

        $this->call_($token, '/api/admin/templates/email/render', $body)->assertOk();
        $this->call_($token, '/api/admin/templates/email/test-send', $body)->assertOk();
        $this->call_($token, '/api/admin/templates/email/images', ['image' => $this->png()])->assertOk();

        $this->switchToTenantWithOwnKeys();

        $this->call_($token, '/api/admin/templates/email/render', $body)->assertUnauthorized();
        $this->call_($token, '/api/admin/templates/email/test-send', $body)->assertUnauthorized();
        $this->call_($token, '/api/admin/templates/email/images', ['image' => $this->png()])->assertUnauthorized();
        Mail::assertSentCount(1);
        // Only the first upload exists: the foreign token stored nothing.
        $this->assertCount(1, Storage::disk()->allFiles('email-images'));
    }

    public function testATokenOfAnotherTenantIsRejectedOnTheTemplateEndpointsThatTakeALanguage(): void
    {
        $this->seed(PermissionTableSeeder::class);
        Mail::fake();
        $token = $this->makeAdmin()->createToken('test')->accessToken;
        $template = Template::query()->where('event', ManuallyTriggeredEvent::class)->where('channel', EmailChannel::class)->firstOrFail();
        $body = ['event' => ManuallyTriggeredEvent::class, 'title' => 'Hi @VarUserName', 'content' => '<p>@VarUserName</p>', 'locale' => 'pl'];
        $created = [
            'name' => 'Polish',
            'locale' => 'pl',
            'event' => ManuallyTriggeredEvent::class,
            'channel' => EmailChannel::class,
            'sections' => [['key' => 'title', 'content' => 'Hi @VarUserName'], ['key' => 'content', 'content' => '<p>@VarUserName</p>']],
        ];

        $this->call_($token, '/api/admin/templates/email/test-send', $body)->assertOk()->assertJsonPath('data.locale', 'pl');
        $this->asToken($token, 'GET', '/api/admin/templates?locale=pl')->assertOk();
        $this->asToken($token, 'GET', '/api/admin/templates/' . $template->getKey())->assertOk();
        $before = Template::count();

        $this->switchToTenantWithOwnKeys();

        $this->call_($token, '/api/admin/templates/email/render', $body)->assertUnauthorized();
        $this->call_($token, '/api/admin/templates/email/test-send', $body)->assertUnauthorized();
        $this->asToken($token, 'GET', '/api/admin/templates?locale=pl')->assertUnauthorized();
        $this->asToken($token, 'GET', '/api/admin/templates/' . $template->getKey())->assertUnauthorized();
        $this->asToken($token, 'POST', '/api/admin/templates', $created)->assertUnauthorized();
        $this->asToken($token, 'PATCH', '/api/admin/templates/' . $template->getKey(), ['locale' => 'zh'])->assertUnauthorized();
        Mail::assertSentCount(1);
        $this->assertSame($before, Template::count());
        $this->assertNull($template->fresh()->locale);
    }

    private function asToken(string $token, string $method, string $url, array $body = [])
    {
        $response = $this->json($method, $url, $body, ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json']);
        Auth::forgetGuards();

        return $response;
    }

    private function png(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('a.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
    }

    private function call_(string $token, string $url, array $body)
    {
        $headers = ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'];
        $response = isset($body['image'])
            ? $this->post($url, $body, $headers)
            : $this->json('POST', $url, $body, $headers);
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
