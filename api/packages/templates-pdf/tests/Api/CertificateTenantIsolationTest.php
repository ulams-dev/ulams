<?php

namespace Ulams\TemplatesPdf\Tests\Api;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Bridge\AccessTokenRepository;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResourceServer;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\TemplatesPdf\Models\FabricPDF;
use Ulams\TemplatesPdf\Tests\TestCase;

/**
 * Tenants have separate databases and their own Passport key pair (ADR 0007): an admin token issued
 * by one tenant is rejected by another. Same technique as packages/adapt/tests/Api/TenantIsolationTest.php.
 */
class CertificateTenantIsolationTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesUsers;

    public function testAnAdminTokenOfAnotherTenantCannotRevoke(): void
    {
        $token = $this->makeAdmin()->createToken('test')->accessToken;
        $pdf = FabricPDF::factory()->createOne(['certificate_id' => 'b3f1c2d4-1111-4222-8333-444455556666']);

        $this->revoke($token, $pdf)->assertOk();

        $this->switchToTenantWithOwnKeys();

        $pdf2 = FabricPDF::factory()->createOne(['certificate_id' => 'c3f1c2d4-1111-4222-8333-444455556666']);
        $this->revoke($token, $pdf2)->assertUnauthorized();
        $this->assertNull($pdf2->fresh()->revoked_at);
    }

    private function revoke(string $token, FabricPDF $pdf)
    {
        $response = $this->postJson("/api/admin/pdfs/{$pdf->getKey()}/revoke", ['reason' => 'test'], ['Authorization' => 'Bearer ' . $token]);
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
