<?php

namespace Ulams\Uploads\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Ulams\Uploads\Tests\TestCase;

class ContentFileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('pkg');
        config([
            'ulams_uploads.content_origin' => 'http://coffee.content.localhost',
            'ulams_uploads.content_disks' => ['scorm' => 'test.scorm_disk', 'liascript' => 'test.liascript_disk'],
            'test.scorm_disk' => 'pkg',
            'test.liascript_disk' => 'pkg',
        ]);
        Storage::disk('pkg')->put('scorm/scorm_12/abc/index.html', '<p>SCO</p>');
        Storage::disk('pkg')->put('scorm/scorm_12/abc/app.js', 'run()');
        Storage::disk('pkg')->put('avatars/me.png', 'PNG');
    }

    public function testServesPackageFilesToTheContentOriginWithTheirTypes(): void
    {
        $this->withHeaders(['X-Ulams-Content-Origin' => '1'])->get('/api/content/scorm/scorm_12/abc/index.html')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->assertHeader('Cross-Origin-Resource-Policy', 'cross-origin');
        $response = $this->withHeaders(['X-Ulams-Content-Origin' => '1'])->get('/api/content/scorm/scorm_12/abc/app.js');
        $response->assertOk()->assertHeader('Content-Type', 'text/javascript; charset=UTF-8');
        $this->assertSame('run()', $response->streamedContent());
    }

    public function testNothingIsServedOnTheApiOriginOrWithoutAContentOrigin(): void
    {
        // a browser opening the API URL directly (no proxy header): package HTML never runs on the API origin
        $this->get('/api/content/scorm/scorm_12/abc/index.html')->assertNotFound();

        config(['ulams_uploads.content_origin' => null, 'scorm.content_origin' => null]);
        $this->withHeaders(['X-Ulams-Content-Origin' => '1'])->get('/api/content/scorm/scorm_12/abc/index.html')->assertNotFound();
    }

    public function testOnlyPackagePrefixesAndNoTraversal(): void
    {
        $headers = ['X-Ulams-Content-Origin' => '1'];
        $this->withHeaders($headers)->get('/api/content/avatars/me.png')->assertNotFound();
        $this->withHeaders($headers)->get('/api/content/scorm')->assertNotFound();
        $this->withHeaders($headers)->get('/api/content/scorm/../avatars/me.png')->assertNotFound();
        $this->withHeaders($headers)->get('/api/content/scorm/%2e%2e/avatars/me.png')->assertNotFound();
        $this->withHeaders($headers)->get('/api/content/scorm/scorm_12/missing.html')->assertNotFound();
    }
}
