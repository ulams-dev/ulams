<?php

namespace Ulams\Identity\Tests\Support;

use Illuminate\Testing\TestResponse;
use Ulams\Identity\Models\IdentityProvider;

/** Drives the browser side of a SAML sign-in against {@see FakeSamlIdp}. Needs the OIDC trait's helpers (start, url, exchange). */
trait RunsSamlLogin
{
    protected function samlBase(?string $host = null): string
    {
        return 'http://' . ($host ?? 'localhost');
    }

    protected function acsUrl(string $slug = 'acme-saml', ?string $host = null): string
    {
        return $this->samlBase($host) . '/api/identity/saml/' . $slug . '/acs';
    }

    protected function spEntityId(string $slug = 'acme-saml', ?string $host = null): string
    {
        return $this->samlBase($host) . '/api/identity/saml/' . $slug . '/metadata';
    }

    /**
     * The AuthnRequest in a redirect to the IdP.
     *
     * @return array{id:string,xml:string,relay:string,url:string,params:array<string,string>}
     */
    protected function authnRequestOf(TestResponse $started): array
    {
        $started->assertRedirect();
        $location = (string) $started->headers->get('Location');
        $this->assertStringStartsWith(FakeSamlIdp::SSO_URL . '?', $location);
        $params = $this->queryOf($location);
        $xml = gzinflate((string) base64_decode($params['SAMLRequest']));
        preg_match('/\bID="([^"]+)"/', $xml, $m);

        return ['id' => $m[1], 'xml' => $xml, 'relay' => $params['RelayState'], 'url' => $location, 'params' => $params];
    }

    /** @param array<string,mixed> $overrides options of {@see FakeSamlIdp::response()} */
    protected function responseFor(array $authn, array $overrides = [], string $slug = 'acme-saml', ?string $host = null): string
    {
        return $this->samlIdp->response($overrides + [
            'acs' => $this->acsUrl($slug, $host),
            'sp' => $this->spEntityId($slug, $host),
            'request_id' => $authn['id'],
        ]);
    }

    protected function postAcs(?string $samlResponse, ?string $relay, string $slug = 'acme-saml', ?string $host = null): TestResponse
    {
        $form = array_filter(['SAMLResponse' => $samlResponse, 'RelayState' => $relay], fn ($v) => $v !== null);

        return $this->post($this->url('api/identity/saml/' . $slug . '/acs', $host), $form);
    }

    /**
     * Starts a login, answers it as the IdP would and posts the response to the ACS.
     *
     * @param array<string,mixed> $overrides options of {@see FakeSamlIdp::response()}
     * @param array{slug?:string,host?:string,relay?:string,response?:string} $options
     * @return array{response:TestResponse,authn:array<string,mixed>,saml:string}
     */
    protected function samlSignIn(array $overrides = [], array $options = []): array
    {
        $slug = $options['slug'] ?? 'acme-saml';
        $host = $options['host'] ?? null;
        $authn = $this->authnRequestOf($this->start($slug, [], $host));
        $saml = $options['response'] ?? $this->responseFor($authn, $overrides, $slug, $host);

        return ['response' => $this->postAcs($saml, $options['relay'] ?? $authn['relay'], $slug, $host), 'authn' => $authn, 'saml' => $saml];
    }

    /** The reason the last SAML failure was recorded with. */
    protected function lastFailure(?IdentityProvider $provider = null): ?string
    {
        $row = \Ulams\Identity\Models\IdentityAudit::query()->where('action', 'sso.login_failed')->orderByDesc('id')->first();

        return $row === null ? null : ($row->data['reason'] ?? null);
    }
}
