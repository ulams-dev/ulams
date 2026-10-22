<?php

namespace Ulams\Identity\Tests\Feature;

use Ulams\Core\Http\SafeHttp;
use Ulams\Identity\Models\IdentityAudit;
use Ulams\Identity\Tests\Support\FakeIdp;
use Ulams\Identity\Tests\Support\RunsOidcLogin;
use Ulams\Identity\Tests\TestCase;

/** What the client accepts from the provider's discovery document, and where it is willing to connect. */
class DiscoveryTest extends TestCase
{
    use RunsOidcLogin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider();
    }

    public function testTheIssuerOfTheDocumentMustBeTheConfiguredOne(): void
    {
        $this->idp->discovery = ['issuer' => FakeIdp::ISSUER . '/'];

        $this->start()->assertStatus(502);
    }

    public function testPlainHttpEndpointsAreRefused(): void
    {
        foreach (['authorization_endpoint', 'token_endpoint', 'jwks_uri'] as $key) {
            $this->idp->discovery = [$key => 'http://idp.example.test/x'];
            \Illuminate\Support\Facades\Cache::flush();
            $this->start()->assertStatus(502);
        }
    }

    public function testAnInsecureUserinfoEndpointIsDroppedNotFollowed(): void
    {
        $this->idp->discovery = ['userinfo_endpoint' => 'http://idp.example.test/userinfo'];
        $this->provider(['slug' => 'ui', 'oidc' => ['userinfo' => true]]);

        $this->signIn([], ['slug' => 'ui']);

        $this->assertCount(0, $this->idp->requestsTo('/userinfo'));
        $this->assertTrue(\Ulams\Auth\Models\User::query()->where('email', 'ada@example.com')->exists());
    }

    public function testAProviderThatDoesNotOfferS256IsRefused(): void
    {
        $this->idp->discovery = ['code_challenge_methods_supported' => ['plain']];

        $this->start()->assertStatus(502);
    }

    public function testAProviderThatDoesNotAdvertiseMethodsIsAccepted(): void
    {
        $this->idp->discovery = ['code_challenge_methods_supported' => null];

        $this->start()->assertStatus(302);
    }

    public function testAProviderThatAnswersWithAnErrorIsNotFollowed(): void
    {
        $this->app->instance('identity.http_handler', fn () => \GuzzleHttp\Promise\Create::promiseFor(new \GuzzleHttp\Psr7\Response(500, [], 'boom')));

        $this->start()->assertStatus(502);
    }

    public function testAnIssuerOnAPrivateAddressIsNeverContacted(): void
    {
        SafeHttp::useResolver(fn (string $host) => ['10.0.0.5']);

        $this->start()->assertStatus(502);
        $this->assertSame([], $this->idp->requests);
    }

    public function testThePrivateAddressGuardCanBeLiftedForDevelopmentOnly(): void
    {
        SafeHttp::useResolver(fn (string $host) => ['10.0.0.5']);
        config(['ulams_identity.allow_insecure_urls' => true]);

        $this->start()->assertStatus(302);
    }

    public function testADiscoveryDocumentIsCachedPerProviderAndIssuer(): void
    {
        $this->start();
        $this->start();

        $this->assertSame(1, $this->idp->discoveryFetches);
    }

    public function testAProviderThatIsDownDuringTheCallbackSendsTheBrowserBackWithAGenericReason(): void
    {
        $started = $this->start();
        $state = $this->queryOf((string) $started->headers->get('Location'))['state'];
        $this->idp->tokenBody = null; // the token endpoint answers 400

        $response = $this->answerCallback('acme', $state);

        $this->assertSame(['error' => 'provider_failed'], $this->queryOf((string) $response->headers->get('Location')));
        $this->assertSame('token_request_failed', IdentityAudit::query()->where('action', 'sso.login_failed')->latest('id')->first()->data['reason']);
    }
}
