<?php

namespace Ulams\Identity\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Ulams\Identity\Events\SamlMetadataNeedsAttention;
use Ulams\Identity\Models\IdentityAudit;
use Ulams\Identity\Models\IdentityProvider;
use Ulams\Identity\Services\ProviderService;
use Ulams\Identity\Tests\Support\FakeSamlIdp;
use Ulams\Identity\Tests\TestCase;

/**
 * IdP metadata refresh (plan 4.3, question 5): new signing certificates are added by themselves; removing the last
 * trusted certificate, a new entity id or a certificate set unrelated to the trusted one need an administrator.
 */
class SamlMetadataRefreshTest extends TestCase
{
    private function fingerprint(string $name): string
    {
        return hash('sha256', (string) base64_decode(FakeSamlIdp::certBody(FakeSamlIdp::pair($name)['cert'])));
    }

    private function fingerprints(IdentityProvider $provider): array
    {
        $fps = array_column((array) $provider->fresh()->saml_idp_certs, 'fingerprint');
        sort($fps);

        return $fps;
    }

    private function serve(array $names, string $entity = FakeSamlIdp::ENTITY_ID, bool $slo = true): void
    {
        $this->samlIdp->metadataBody = $this->samlIdp->metadata(array_map(fn (string $n) => FakeSamlIdp::pair($n)['cert'], $names), $entity, $slo);
        $this->app->instance('identity.http_handler', $this->samlIdp->handler());
    }

    private function refresh(IdentityProvider $provider, bool $accept = false, string $actor = 'system'): array
    {
        return $this->app->make(ProviderService::class)->refreshMetadata($provider->fresh(), $accept, $actor);
    }

    private function sorted(array ...$names): array
    {
        $fps = array_map(fn (string $n) => $this->fingerprint($n), $names[0]);
        sort($fps);

        return $fps;
    }

    public function testNothingChangesWhenTheMetadataIsTheSame(): void
    {
        $provider = $this->samlProvider();
        $this->serve(['idp']);

        $result = $this->refresh($provider);

        $this->assertSame('unchanged', $result['status']);
        $this->assertSame(0, IdentityAudit::query()->where('action', 'saml.metadata_refreshed')->count());
        $this->assertNotNull($provider->fresh()->metadata_refreshed_at);
    }

    public function testANewCertificateIsAddedNextToTheOldOne(): void
    {
        $provider = $this->samlProvider();
        $this->serve(['idp', 'idp-next']);

        $result = $this->refresh($provider);

        $this->assertSame('updated', $result['status']);
        $this->assertSame([$this->fingerprint('idp-next')], $result['added']);
        $this->assertSame($this->sorted(['idp', 'idp-next']), $this->fingerprints($provider));
        $row = IdentityAudit::query()->where('action', 'saml.metadata_refreshed')->firstOrFail();
        $this->assertSame('daily_refresh', $row->data['via']);
        $this->assertSame([$this->fingerprint('idp-next')], $row->data['added']);
    }

    public function testAResponseSignedWithTheNewCertificateWorksAfterTheRefresh(): void
    {
        $provider = $this->samlProvider();
        $this->serve(['idp', 'idp-next']);
        $this->refresh($provider);

        $this->assertContains($this->fingerprint('idp-next'), $this->fingerprints($provider));
    }

    public function testACertificateTheIdpStoppedPublishingIsRemovedWhileAnotherTrustedOneStays(): void
    {
        $provider = $this->samlProvider();
        $this->serve(['idp', 'idp-next']);
        $this->refresh($provider);

        // rotation done at the IdP: only the new one is published, the old one is still trusted until now
        $this->serve(['idp-next']);
        $result = $this->refresh($provider);

        $this->assertSame('updated', $result['status']);
        $this->assertSame([$this->fingerprint('idp')], $result['removed']);
        $this->assertSame([$this->fingerprint('idp-next')], $this->fingerprints($provider));
    }

    public function testCertificatesUnrelatedToTheTrustedOnesAreAddedButNothingIsDroppedAndAnAdminIsToldToLook(): void
    {
        Event::fake([SamlMetadataNeedsAttention::class]);
        $provider = $this->samlProvider();
        $this->serve(['attacker']);

        $result = $this->refresh($provider);

        $this->assertSame('attention', $result['status']);
        $this->assertSame('metadata_certificates_unrelated', $result['reason']);
        $this->assertSame($this->sorted(['idp', 'attacker']), $this->fingerprints($provider), 'the trusted certificate stays');
        $this->assertSame('metadata_certificates_unrelated', $provider->fresh()->last_error);
        $row = IdentityAudit::query()->where('action', 'saml.metadata_refreshed')->firstOrFail();
        $this->assertTrue($row->data['no_overlap']);
        Event::assertDispatched(SamlMetadataNeedsAttention::class, fn ($e) => $e->slug === 'acme-saml');
    }

    public function testAChangedEntityIdIsNotAppliedWithoutAnAdministrator(): void
    {
        Event::fake([SamlMetadataNeedsAttention::class]);
        $provider = $this->samlProvider();
        $this->serve(['idp', 'idp-next'], 'https://evil.example.test/idp');

        $result = $this->refresh($provider);

        $this->assertSame('attention', $result['status']);
        $this->assertSame('entity_id_changed', $result['reason']);
        $fresh = $provider->fresh();
        $this->assertSame(FakeSamlIdp::ENTITY_ID, $fresh->saml['entity_id']);
        $this->assertSame([$this->fingerprint('idp')], $this->fingerprints($provider), 'nothing of the new document is taken over');
        $this->assertSame('metadata_entity_id_changed', $fresh->last_error);
        $this->assertSame(1, IdentityAudit::query()->where('action', 'saml.metadata_attention')->count());
        Event::assertDispatched(SamlMetadataNeedsAttention::class);
    }

    public function testAnAdministratorCanAcceptANewEntityIdAndANewCertificateSet(): void
    {
        $provider = $this->samlProvider();
        $this->serve(['idp-next'], 'https://new.example.test/idp');

        $result = $this->refresh($provider, true, 'user');

        $this->assertSame('updated', $result['status']);
        $fresh = $provider->fresh();
        $this->assertSame('https://new.example.test/idp', $fresh->saml['entity_id']);
        $this->assertSame([$this->fingerprint('idp-next')], $this->fingerprints($provider));
        $row = IdentityAudit::query()->where('action', 'saml.metadata_refreshed')->firstOrFail();
        $this->assertSame('admin', $row->data['via']);
        $this->assertTrue($row->data['entity_changed']);
    }

    public function testTheLastTrustedCertificateIsNeverRemovedByInvalidMetadata(): void
    {
        $provider = $this->samlProvider();
        // metadata without any signing certificate
        $this->samlIdp->metadataBody = '<?xml version="1.0"?><md:EntityDescriptor xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata" entityID="' . FakeSamlIdp::ENTITY_ID . '"><md:IDPSSODescriptor protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol"><md:SingleSignOnService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect" Location="' . FakeSamlIdp::SSO_URL . '"/></md:IDPSSODescriptor></md:EntityDescriptor>';
        $this->app->instance('identity.http_handler', $this->samlIdp->handler());

        $result = $this->refresh($provider);

        $this->assertSame('failed', $result['status']);
        $this->assertSame([$this->fingerprint('idp')], $this->fingerprints($provider));
        $this->assertSame('metadata_metadata_invalid', $provider->fresh()->last_error);
    }

    public function testAnUnreachableMetadataUrlChangesNothingAndIsRecorded(): void
    {
        $provider = $this->samlProvider();
        $this->samlIdp->metadataStatus = 500;
        $this->app->instance('identity.http_handler', $this->samlIdp->handler());

        $result = $this->refresh($provider);

        $this->assertSame('failed', $result['status']);
        $this->assertSame('metadata_unreachable', $result['reason']);
        $this->assertSame([$this->fingerprint('idp')], $this->fingerprints($provider));
        $this->assertSame(1, IdentityAudit::query()->where('action', 'saml.metadata_failed')->count());
    }

    public function testMetadataWithADoctypeIsNotParsed(): void
    {
        $provider = $this->samlProvider();
        $this->samlIdp->metadataBody = '<?xml version="1.0"?><!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]>' . $this->samlIdp->metadata();
        $this->app->instance('identity.http_handler', $this->samlIdp->handler());

        $this->assertSame('failed', $this->refresh($provider)['status']);
    }

    public function testAChangedSignOnUrlIsFollowed(): void
    {
        $provider = $this->samlProvider(saml: ['sso_url' => 'https://old.example.test/sso']);
        $this->serve(['idp']);

        $this->assertSame('updated', $this->refresh($provider)['status']);
        $this->assertSame(FakeSamlIdp::SSO_URL, $provider->fresh()->saml['sso_url']);
    }

    public function testTheCommandRefreshesEnabledSamlProvidersOnly(): void
    {
        $this->samlProvider();
        $this->samlProvider(['slug' => 'off', 'enabled' => false]);
        $this->provider(['slug' => 'oidc-one']);
        $this->serve(['idp', 'idp-next']);

        $this->artisan('identity:refresh-metadata')->expectsOutputToContain('acme-saml: updated')->assertExitCode(0);

        $this->assertSame(1, IdentityAudit::query()->where('action', 'saml.metadata_refreshed')->count());
    }

    public function testTheCommandFailsWhenAProviderNeedsAttention(): void
    {
        $this->samlProvider();
        $this->serve(['idp'], 'https://evil.example.test/idp');

        $this->artisan('identity:refresh-metadata')->expectsOutputToContain('entity_id_changed')->assertExitCode(1);
    }

    public function testThePruneCommandDropsOldReplayRecordsAndSessions(): void
    {
        $provider = $this->samlProvider();
        \Ulams\Identity\Models\SamlReplay::query()->create(['provider_id' => $provider->id, 'id_hash' => hash('sha256', 'old'), 'expires_at' => now()->subDay()]);
        \Ulams\Identity\Models\SamlReplay::query()->create(['provider_id' => $provider->id, 'id_hash' => hash('sha256', 'new'), 'expires_at' => now()->addMinutes(5)]);

        $this->artisan('identity:prune')->assertExitCode(0);

        $this->assertSame([hash('sha256', 'new')], \Ulams\Identity\Models\SamlReplay::query()->pluck('id_hash')->all());
    }
}
