<?php

namespace Ulams\TemplatesPdf\Tests\Api;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\TemplatesPdf\Events\CertificateRevoked;
use Ulams\TemplatesPdf\Models\FabricPDF;
use Ulams\TemplatesPdf\Tests\TestCase;

class CertificateVerificationTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesUsers;

    private function certificate(array $attributes = []): FabricPDF
    {
        $student = $this->makeStudent(['first_name' => 'Ada', 'last_name' => 'Lovelace']);

        return FabricPDF::factory()->createOne(array_merge([
            'user_id' => $student->getKey(),
            'title' => 'Certificate for Safety',
            'certificate_id' => 'a3f1c2d4-1111-4222-8333-444455556666',
            'vars' => ['@VarCourseTitle' => 'Safety', '@VarAppName' => 'Acme Academy', '@VarUserEmail' => 'secret@example.com'],
        ], $attributes));
    }

    public function testValidCertificateIsVerifiedWithoutLogin(): void
    {
        $pdf = $this->certificate(['expires_at' => now()->addYear()]);

        $this->getJson('/api/certificates/verify/' . $pdf->certificate_id)
            ->assertOk()
            ->assertJsonPath('data.status', 'valid')
            ->assertJsonPath('data.course_title', 'Safety')
            ->assertJsonPath('data.holder_name', 'Ada Lovelace')
            ->assertJsonPath('data.issuer', 'Acme Academy')
            ->assertJsonMissingPath('data.vars')
            ->assertJsonMissingPath('data.user_id')
            ->assertJsonMissingPath('data.path');
    }

    public function testCertificateWithoutExpiryNeverExpires(): void
    {
        $pdf = $this->certificate();

        $this->getJson('/api/certificates/verify/' . $pdf->certificate_id)
            ->assertOk()
            ->assertJsonPath('data.status', 'valid')
            ->assertJsonPath('data.expires_at', null);
    }

    public function testExpiredCertificateIsReportedExpired(): void
    {
        $pdf = $this->certificate(['expires_at' => now()->subDay()]);

        $this->getJson('/api/certificates/verify/' . $pdf->certificate_id)
            ->assertOk()
            ->assertJsonPath('data.status', 'expired');
    }

    public function testRevokedCertificateStaysVerifiableAsRevoked(): void
    {
        $pdf = $this->certificate(['expires_at' => now()->subDay(), 'revoked_at' => now()]);

        $this->getJson('/api/certificates/verify/' . $pdf->certificate_id)
            ->assertOk()
            ->assertJsonPath('data.status', 'revoked');
    }

    public function testUnknownIdIs404(): void
    {
        $this->getJson('/api/certificates/verify/00000000-0000-4000-8000-000000000000')->assertNotFound();
        $this->getJson('/api/certificates/verify/' . $this->certificate()->getKey())->assertNotFound();
    }

    public function testHolderNameCanBeMasked(): void
    {
        $pdf = $this->certificate();

        config(['ulams_templates_pdf.verify.holder_name' => 'initials']);
        $this->getJson('/api/certificates/verify/' . $pdf->certificate_id)->assertJsonPath('data.holder_name', 'A. L.');

        config(['ulams_templates_pdf.verify.holder_name' => 'none']);
        $this->getJson('/api/certificates/verify/' . $pdf->certificate_id)->assertJsonPath('data.holder_name', null);
    }

    public function testVerificationIsThrottled(): void
    {
        $pdf = $this->certificate();

        $last = null;
        for ($i = 0; $i < 70; $i++) {
            $last = $this->getJson('/api/certificates/verify/' . $pdf->certificate_id);
        }
        $last->assertStatus(429);
    }

    public function testAdminCanRevokeWithReason(): void
    {
        Event::fake([CertificateRevoked::class]);
        $pdf = $this->certificate();

        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson("/api/admin/pdfs/{$pdf->getKey()}/revoke", ['reason' => 'Policy superseded'])
            ->assertOk()
            ->assertJsonPath('data.status', 'revoked');

        $pdf->refresh();
        $this->assertSame('Policy superseded', $pdf->revoked_reason);
        $this->assertNotNull($pdf->revoked_by);
        Event::assertDispatched(CertificateRevoked::class, 1);

        // revoking twice keeps the first record and does not repeat the event
        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson("/api/admin/pdfs/{$pdf->getKey()}/revoke", ['reason' => 'again'])->assertOk();
        $this->assertSame('Policy superseded', $pdf->fresh()->revoked_reason);
        Event::assertDispatched(CertificateRevoked::class, 1);
    }

    public function testRevokeNeedsPermissionAndReason(): void
    {
        $pdf = $this->certificate();

        $this->postJson("/api/admin/pdfs/{$pdf->getKey()}/revoke", ['reason' => 'x'])->assertUnauthorized();
        $this->actingAs($this->makeStudent(), 'api')
            ->postJson("/api/admin/pdfs/{$pdf->getKey()}/revoke", ['reason' => 'x'])->assertForbidden();
        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson("/api/admin/pdfs/{$pdf->getKey()}/revoke", [])->assertStatus(422);
        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/admin/pdfs/999999/revoke', ['reason' => 'x'])->assertNotFound();

        $this->assertNull($pdf->fresh()->revoked_at);
    }

    public function testOwnerSeesStatusAndExpiryInTheirList(): void
    {
        $pdf = $this->certificate(['expires_at' => now()->addDays(10)]);

        $this->actingAs($pdf->user, 'api')->getJson('/api/pdfs')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'valid')
            ->assertJsonPath('data.0.id', $pdf->getKey());
    }
}
