<?php

namespace Ulams\TemplatesPdf\Tests\Api;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Events\CourseFinished;
use Ulams\TemplatesPdf\Pdfme\CertificateTemplates;
use Ulams\TemplatesPdf\Tests\TestCase;

class PdfPreviewTest extends TestCase
{
    use CreatesUsers, DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();
        if (!class_exists(\Ulams\Courses\UlamsCourseServiceProvider::class)) {
            $this->markTestSkipped('Courses package not installed');
        }
        $this->fakePdfService();
    }

    public function testAdminPreviewsUnsavedTemplateWithSampleData(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin, 'api')->post('/api/admin/pdfs/preview', [
            'event' => CourseFinished::class,
            'content' => CertificateTemplates::template('coffee'),
        ]);

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('inline;', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());

        Http::assertSent(function (Request $request) {
            $inputs = $request->data()['inputs'][0];
            return $request->url() === self::PDF_SERVICE . '/render'
                && $inputs['@VarUserName'] !== ''
                && $inputs['@VarAppName'] === config('app.name')
                && str_contains($inputs['@VarCertificateVerifyUrl'], '/certificates/verify/');
        });
    }

    public function testPreviewAcceptsJsonStringContent(): void
    {
        $this->actingAs($this->makeAdmin(), 'api')->postJson('/api/admin/pdfs/preview', [
            'event' => CourseFinished::class,
            'content' => CertificateTemplates::content(),
        ])->assertOk();
    }

    public function testPreviewRequiresTemplatePermissions(): void
    {
        $body = ['event' => CourseFinished::class, 'content' => CertificateTemplates::template()];

        $this->postJson('/api/admin/pdfs/preview', $body)->assertUnauthorized();
        $this->actingAs($this->makeStudent(), 'api')->postJson('/api/admin/pdfs/preview', $body)->assertForbidden();
        Http::assertNothingSent();
    }

    public function testPreviewReportsInvalidInput(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'api')->postJson('/api/admin/pdfs/preview', ['event' => CourseFinished::class, 'content' => 'nope'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content');

        $this->actingAs($admin, 'api')->postJson('/api/admin/pdfs/preview', ['event' => 'Unknown\\Event', 'content' => CertificateTemplates::template()])
            ->assertUnprocessable()
            ->assertJsonFragment(['error' => 'unknown_event']);

        $this->actingAs($admin, 'api')->postJson('/api/admin/pdfs/preview', [
            'event' => CourseFinished::class,
            'content' => ['docElements' => [], 'documentProperties' => [], 'parameters' => []],
        ])->assertUnprocessable()->assertJsonFragment(['error' => 'legacy_template']);

        $this->fakePdfService(503);
        $this->actingAs($admin, 'api')->postJson('/api/admin/pdfs/preview', ['event' => CourseFinished::class, 'content' => CertificateTemplates::template()])
            ->assertStatus(503);
    }

    public function testFontsAreProxiedFromTheRenderer(): void
    {
        File::deleteDirectory(storage_path('app/pdf-fonts'));

        $this->getJson('/api/pdfs/fonts')
            ->assertOk()
            ->assertJsonFragment(['name' => 'NotoSans-Regular', 'file' => 'NotoSans-Regular.ttf', 'fallback' => true]);

        $response = $this->get('/api/pdfs/fonts/NotoSans-Regular.ttf');
        $response->assertOk();
        $this->assertSame('font/ttf', $response->headers->get('Content-Type'));
        $this->assertSame('TTF-BYTES', $response->getFile()->getContent());

        // cached on disk: no second request
        $this->get('/api/pdfs/fonts/NotoSans-Regular.ttf')->assertOk();
        Http::assertSentCount(2);

        $this->get('/api/pdfs/fonts/..%2F.env')->assertNotFound();
        $this->get('/api/pdfs/fonts/evil.php')->assertNotFound();

        File::deleteDirectory(storage_path('app/pdf-fonts'));
    }
}
