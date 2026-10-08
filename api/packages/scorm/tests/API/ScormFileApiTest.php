<?php

namespace Ulams\Scorm\Tests\API;

use Illuminate\Support\Facades\Storage;
use Ulams\Scorm\Tests\TestCase;

/**
 * Package files on the local SCORM disk are served from <disk url>/scorm/...
 */
class ScormFileApiTest extends TestCase
{
    private const DIR = 'scorm/scorm_12/b62f0754-1989-4c1d-b414-bb97f523e620';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::disk('local')->put(self::DIR . '/index.html', '<html><script src="js/app.js"></script></html>');
        Storage::disk('local')->put(self::DIR . '/js/app.js', 'window.ok = true;');
        Storage::disk('local')->put('outside.txt', 'secret');
    }

    public function testServesPackageFilesWithTheirContentType(): void
    {
        $html = $this->get('/storage/' . self::DIR . '/index.html');
        $html->assertOk();
        $this->assertStringStartsWith('text/html', $html->headers->get('Content-Type'));
        $this->assertStringContainsString('<script src="js/app.js">', $html->streamedContent());

        $js = $this->get('/storage/' . self::DIR . '/js/app.js');
        $js->assertOk();
        $this->assertStringStartsWith('text/javascript', $js->headers->get('Content-Type'));
        $this->assertSame('nosniff', $js->headers->get('X-Content-Type-Options'));
    }

    public function testMissingFilesAndDirectoriesAre404(): void
    {
        $this->get('/storage/' . self::DIR . '/missing.html')->assertNotFound();
        $this->get('/storage/' . self::DIR)->assertNotFound();
        $this->get('/storage/scorm/')->assertNotFound();
    }

    public function testCannotLeaveTheScormDirectory(): void
    {
        $this->get('/storage/scorm/../outside.txt')->assertNotFound();
        $this->get('/storage/scorm/%2e%2e/outside.txt')->assertNotFound();
        $this->get('/storage/scorm/scorm_12/..%2f..%2foutside.txt')->assertNotFound();
    }

    public function test_files_are_not_served_on_the_api_origin_when_a_content_origin_exists(): void
    {
        config(['scorm.content_origin' => 'http://coffee.content.localhost']);

        $this->get('/storage/' . self::DIR . '/index.html')->assertNotFound();
        $this->withHeaders(['X-Ulams-Content-Origin' => '1'])->get('/api/content/' . self::DIR . '/index.html')->assertOk();
    }
}
