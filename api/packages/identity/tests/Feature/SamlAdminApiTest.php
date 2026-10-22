<?php

namespace Ulams\Identity\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Ulams\Identity\Models\IdentityAudit;
use Ulams\Identity\Models\IdentityProvider;
use Ulams\Identity\Tests\Support\FakeSamlIdp;
use Ulams\Identity\Tests\Support\RunsOidcLogin;
use Ulams\Identity\Tests\Support\RunsSamlLogin;
use Ulams\Identity\Tests\TestCase;

/** The admin API for SAML providers: metadata import, certificates, SHA-1 opt-in, service provider keys. */
class SamlAdminApiTest extends TestCase
{
    use RunsOidcLogin;
    use RunsSamlLogin;

    private const BASE = '/api/admin/identity/providers';

    private function admin2()
    {
        return $this->actingAs($this->admin(), 'api');
    }

    private function create(array $overrides = [])
    {
        $payload = array_replace_recursive([
            'protocol' => 'saml',
            'slug' => 'acme-saml',
            'name' => 'Acme SAML',
            'enabled' => true,
            'email_domains' => ['example.com'],
            'trust_email' => true,
            'saml' => ['metadata_url' => FakeSamlIdp::METADATA_URL],
        ], $overrides);

        return $this->admin2()->postJson(self::BASE, $payload);
    }

    private function useMetadata(?string $xml = null): void
    {
        $this->samlIdp->metadataBody = $xml;
        $this->app->instance('identity.http_handler', $this->samlIdp->handler());
    }

    public function testCreatingFromAMetadataUrlReadsEntityEndpointsAndCertificate(): void
    {
        $this->useMetadata();

        $response = $this->create()->assertCreated();

        $data = $response->json('data');
        $this->assertSame('saml', $data['protocol']);
        $this->assertNull($data['oidc']);
        $this->assertSame(FakeSamlIdp::ENTITY_ID, $data['saml']['entity_id']);
        $this->assertSame(FakeSamlIdp::SSO_URL, $data['saml']['sso_url']);
        $this->assertSame(FakeSamlIdp::SLO_URL, $data['saml']['slo_url']);
        $this->assertSame(FakeSamlIdp::METADATA_URL, $data['saml']['metadata_url']);
        $this->assertSame('urn:oasis:names:tc:SAML:2.0:nameid-format:persistent', $data['saml']['name_id_format']);
        $this->assertCount(1, $data['saml']['idp_certificates']);
        $this->assertSame(hash('sha256', (string) base64_decode(FakeSamlIdp::certBody($this->samlIdp->signing['cert']))), $data['saml']['idp_certificates'][0]['fingerprint']);
        $this->assertSame('metadata', $data['saml']['idp_certificates'][0]['source']);
        $this->assertNotNull($data['metadata_refreshed_at']);
        $this->assertSame('http://localhost/api/identity/saml/acme-saml/metadata', $data['saml']['sp']['entity_id']);
        $this->assertSame('http://localhost/api/identity/saml/acme-saml/acs', $data['saml']['sp']['acs_url']);
        $this->assertSame('http://localhost/api/identity/saml/acme-saml/slo', $data['saml']['sp']['slo_url']);
        $this->assertNotEmpty($data['saml']['sp']['certificate_fingerprint']);
        $this->assertNull($data['saml']['sp']['next_certificate_fingerprint']);
        $this->assertNull($data['redirect_uri']);
        $this->assertSame(['provider.created', 'saml.certs_changed'], IdentityAudit::query()->orderBy('id')->pluck('action')->all());
    }

    public function testCreatingFromUploadedMetadata(): void
    {
        $data = $this->create(['saml' => ['metadata_url' => null, 'metadata_xml' => $this->samlIdp->metadata()]])->assertCreated()->json('data');

        $this->assertSame(FakeSamlIdp::ENTITY_ID, $data['saml']['entity_id']);
        $this->assertNull($data['saml']['metadata_url']);
    }

    public function testCreatingByHandWithCertificates(): void
    {
        $data = $this->create(['saml' => [
            'metadata_url' => null,
            'entity_id' => 'https://manual.example.test/idp',
            'sso_url' => 'https://manual.example.test/sso',
            'idp_certificates' => [$this->samlIdp->signing['cert']],
        ]])->assertCreated()->json('data');

        $this->assertSame('https://manual.example.test/idp', $data['saml']['entity_id']);
        $this->assertSame('admin', $data['saml']['idp_certificates'][0]['source']);
    }

    public function testThePrivateKeysNeverLeaveTheApiAndAreEncryptedAtRest(): void
    {
        $this->useMetadata();
        $id = $this->create()->assertCreated()->json('data.id');
        $this->artisan('identity:prune')->assertExitCode(0);

        $row = IdentityProvider::query()->findOrFail($id);
        $key = $row->saml_sp_key;
        $this->assertStringContainsString('PRIVATE KEY', $key);
        $raw = (string) DB::table('identity_providers')->where('id', $id)->value('saml_sp_key');
        $this->assertStringNotContainsString('PRIVATE KEY', $raw);
        $body = trim(str_replace(["\n", '-----BEGIN PRIVATE KEY-----', '-----END PRIVATE KEY-----', '-----BEGIN RSA PRIVATE KEY-----', '-----END RSA PRIVATE KEY-----'], '', $key));

        $admin = $this->admin();
        foreach ([self::BASE, self::BASE . '/' . $id, '/api/admin/identity/audit', '/api/identity/providers', '/api/identity/saml/acme-saml/metadata'] as $uri) {
            $content = $this->actingAs($admin, 'api')->get($uri)->getContent();
            $this->assertStringNotContainsString('PRIVATE KEY', $content, $uri);
            $this->assertStringNotContainsString(substr($body, 40, 60), $content, $uri);
        }
        $this->assertStringNotContainsString('PRIVATE KEY', json_encode($row->toArray()));
        $this->assertStringNotContainsString('PRIVATE KEY', json_encode(DB::table('identity_audit')->get()));
    }

    public function testEachProviderGetsItsOwnKeyPair(): void
    {
        $this->useMetadata();
        $this->create()->assertCreated();
        $second = $this->create(['slug' => 'second'])->assertCreated();

        $this->assertNotNull($second->json('data.saml.sp.certificate_fingerprint'));
        $this->assertSame(2, IdentityProvider::query()->whereNotNull('saml_sp_key')->count());
    }

    public function testInvalidMetadataIsRefusedWithAReadableMessage(): void
    {
        $this->useMetadata('<html>not metadata</html>');

        $this->create()->assertStatus(422)->assertJsonValidationErrors(['saml.metadata_url']);
        $this->create(['saml' => ['metadata_url' => null, 'metadata_xml' => '<?xml version="1.0"?><nope/>']])->assertStatus(422)->assertJsonValidationErrors(['saml.metadata_xml']);
        $this->assertSame(0, IdentityProvider::query()->count());
    }

    public function testAMetadataUrlThatIsNotPublicHttpsIsRefused(): void
    {
        $this->create(['saml' => ['metadata_url' => 'http://idp.example.test/metadata']])->assertStatus(422)->assertJsonValidationErrors(['saml.metadata_url']);
        $this->create(['saml' => ['metadata_url' => 'https://user:pass@idp.example.test/metadata']])->assertStatus(422)->assertJsonValidationErrors(['saml.metadata_url']);
        $this->create(['saml' => ['metadata_url' => 'file:///etc/passwd']])->assertStatus(422)->assertJsonValidationErrors(['saml.metadata_url']);
    }

    public function testAMetadataUrlOnAPrivateAddressIsRefusedBySsrfGuard(): void
    {
        \Ulams\Core\Http\SafeHttp::useResolver(fn (string $host) => ['10.0.0.5']);
        $this->app->forgetInstance('identity.http_handler');
        $this->app->offsetUnset('identity.http_handler');

        $this->create(['saml' => ['metadata_url' => 'https://internal.example.test/metadata']])->assertStatus(422)->assertJsonValidationErrors(['saml.metadata_url']);
    }

    public function testAWeakCertificateIsRefused(): void
    {
        $weak = FakeSamlIdp::pair('weak', 1024);

        $this->create(['saml' => ['metadata_url' => null, 'entity_id' => 'https://x.example.test/idp', 'sso_url' => 'https://x.example.test/sso', 'idp_certificates' => [$weak['cert']]]])
            ->assertStatus(422)->assertJsonValidationErrors(['saml.idp_certificates']);
        $this->create(['saml' => ['metadata_url' => null, 'entity_id' => 'https://x.example.test/idp', 'sso_url' => 'https://x.example.test/sso', 'idp_certificates' => ['not a certificate']]])
            ->assertStatus(422)->assertJsonValidationErrors(['saml.idp_certificates']);
    }

    public function testAProviderCannotBeEnabledWithoutMetadataOrCertificates(): void
    {
        $this->create(['saml' => ['metadata_url' => null]])->assertStatus(422)->assertJsonValidationErrors(['enabled']);
        $this->create(['saml' => ['metadata_url' => null], 'enabled' => false])->assertCreated();
    }

    public function testTheSha1OptInIsAuditedWithAWarning(): void
    {
        $this->useMetadata();
        $created = $this->create(['saml' => ['allow_sha1' => true]])->assertCreated();
        $this->assertTrue($created->json('data.saml.allow_sha1'));

        $row = IdentityAudit::query()->where('action', 'saml.sha1_changed')->firstOrFail();
        $this->assertTrue($row->data['allowed']);
        $this->assertStringContainsString('SHA-1', $row->data['warning']);

        $this->actingAs($this->admin(), 'api')->putJson(self::BASE . '/' . $created->json('data.id'), ['saml' => ['allow_sha1' => false]])->assertOk();
        $this->assertSame(2, IdentityAudit::query()->where('action', 'saml.sha1_changed')->count());
        $this->assertFalse(IdentityProvider::query()->firstOrFail()->saml['allow_sha1']);
    }

    public function testUpdatingKeepsWhatIsNotSentAndReplacesCertificatesWhenSent(): void
    {
        $this->useMetadata();
        $id = $this->create()->assertCreated()->json('data.id');
        $next = FakeSamlIdp::pair('idp-next');

        $data = $this->actingAs($this->admin(), 'api')->putJson(self::BASE . '/' . $id, ['name' => 'Renamed', 'saml' => ['idp_certificates' => [$next['cert']], 'attribute_map' => ['email' => 'urn:mail']]])->assertOk()->json('data');

        $this->assertSame('Renamed', $data['name']);
        $this->assertSame(FakeSamlIdp::ENTITY_ID, $data['saml']['entity_id']);
        $this->assertSame(FakeSamlIdp::SSO_URL, $data['saml']['sso_url']);
        $this->assertCount(1, $data['saml']['idp_certificates']);
        $this->assertSame(hash('sha256', (string) base64_decode(FakeSamlIdp::certBody($next['cert']))), $data['saml']['idp_certificates'][0]['fingerprint']);
        $this->assertSame(['email' => 'urn:mail'], (array) $data['saml']['attribute_map']);
        $this->assertSame(2, IdentityAudit::query()->where('action', 'saml.certs_changed')->count(), 'the import at creation and the replacement');
    }

    public function testTheProtocolIsFixedAfterCreation(): void
    {
        $this->useMetadata();
        $id = $this->create()->assertCreated()->json('data.id');

        $data = $this->actingAs($this->admin(), 'api')->putJson(self::BASE . '/' . $id, ['protocol' => 'oidc', 'name' => 'x'])->assertOk()->json('data');

        $this->assertSame('saml', $data['protocol']);
    }

    public function testTheNextServiceProviderKeyIsPreparedPublishedAndThenPromoted(): void
    {
        $this->useMetadata();
        $created = $this->create()->assertCreated();
        $id = $created->json('data.id');
        $current = $created->json('data.saml.sp.certificate_fingerprint');
        $admin = $this->admin();

        $prepared = $this->actingAs($admin, 'api')->postJson(self::BASE . "/$id/sp-keys/rotate", ['step' => 'prepare'])->assertOk()->json('data');
        $this->assertSame('prepare', $prepared['step']);
        $shown = $this->actingAs($admin, 'api')->getJson(self::BASE . "/$id")->json('data.saml.sp');
        $this->assertSame($current, $shown['certificate_fingerprint']);
        $this->assertSame($prepared['fingerprint'], $shown['next_certificate_fingerprint']);
        $this->assertNotSame($current, $prepared['fingerprint']);
        $this->assertStringContainsString(FakeSamlIdp::certBody(IdentityProvider::query()->findOrFail($id)->saml_sp_next_cert), $this->get('/api/identity/saml/acme-saml/metadata')->getContent());

        $this->actingAs($admin, 'api')->postJson(self::BASE . "/$id/sp-keys/rotate", ['step' => 'promote'])->assertOk();
        $after = $this->actingAs($admin, 'api')->getJson(self::BASE . "/$id")->json('data.saml.sp');
        $this->assertSame($prepared['fingerprint'], $after['certificate_fingerprint']);
        $this->assertNull($after['next_certificate_fingerprint']);
        $this->assertSame(2, IdentityAudit::query()->where('action', 'saml.sp_keys_rotated')->count());

        $this->actingAs($admin, 'api')->postJson(self::BASE . "/$id/sp-keys/rotate", ['step' => 'promote'])->assertStatus(422);
        $this->actingAs($admin, 'api')->postJson(self::BASE . "/$id/sp-keys/rotate", ['step' => 'other'])->assertStatus(422);
        $this->actingAs($admin, 'api')->postJson(self::BASE . "/$id/sp-keys/rotate", [])->assertStatus(422);
    }

    public function testOnlySamlProvidersHaveServiceProviderKeys(): void
    {
        $oidc = $this->provider();

        $this->actingAs($this->admin(), 'api')->postJson(self::BASE . "/{$oidc->id}/sp-keys/rotate", ['step' => 'prepare'])->assertStatus(422);
    }

    public function testRefreshingTheMetadataThroughTheApi(): void
    {
        $this->useMetadata();
        $id = $this->create()->assertCreated()->json('data.id');
        $this->useMetadata($this->samlIdp->metadata([$this->samlIdp->signing['cert'], FakeSamlIdp::pair('idp-next')['cert']]));
        $admin = $this->admin();

        $result = $this->actingAs($admin, 'api')->postJson(self::BASE . "/$id/metadata/refresh")->assertOk()->json('data');

        $this->assertSame('updated', $result['status']);
        $this->assertCount(1, $result['added']);
        $this->assertCount(2, $this->actingAs($admin, 'api')->getJson(self::BASE . "/$id")->json('data.saml.idp_certificates'));

        // another entity id needs the administrator's explicit say
        $this->useMetadata($this->samlIdp->metadata(null, 'https://new.example.test/idp'));
        $blocked = $this->actingAs($admin, 'api')->postJson(self::BASE . "/$id/metadata/refresh")->assertOk()->json('data');
        $this->assertSame('attention', $blocked['status']);
        $this->assertSame('entity_id_changed', $blocked['reason']);
        $accepted = $this->actingAs($admin, 'api')->postJson(self::BASE . "/$id/metadata/refresh", ['accept_changes' => true])->assertOk()->json('data');
        $this->assertSame('updated', $accepted['status']);
        $this->assertSame('https://new.example.test/idp', $this->actingAs($admin, 'api')->getJson(self::BASE . "/$id")->json('data.saml.entity_id'));
    }

    public function testRefreshNeedsASamlProviderWithAMetadataUrl(): void
    {
        $oidc = $this->provider();
        $manual = $this->create(['slug' => 'manual', 'saml' => ['metadata_url' => null, 'metadata_xml' => $this->samlIdp->metadata()]])->assertCreated()->json('data.id');
        $admin = $this->admin();

        $this->actingAs($admin, 'api')->postJson(self::BASE . "/{$oidc->id}/metadata/refresh")->assertStatus(422);
        $this->actingAs($admin, 'api')->postJson(self::BASE . "/$manual/metadata/refresh")->assertStatus(422);
    }

    public function testMetadataRefreshAndKeyRotationNeedThePermission(): void
    {
        $this->useMetadata();
        $id = $this->create()->assertCreated()->json('data.id');
        $student = $this->student();

        $this->actingAs($student, 'api')->postJson(self::BASE . "/$id/metadata/refresh")->assertStatus(403);
        $this->actingAs($student, 'api')->postJson(self::BASE . "/$id/sp-keys/rotate", ['step' => 'prepare'])->assertStatus(403);
        $this->assertNull(IdentityProvider::query()->findOrFail($id)->saml_sp_next_key);
    }
}
