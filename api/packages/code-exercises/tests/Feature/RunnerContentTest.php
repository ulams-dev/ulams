<?php

namespace Ulams\CodeExercises\Tests\Feature;

use Ulams\CodeExercises\Services\RunnerHeaders;
use Ulams\CodeExercises\Tests\TestCase;

/**
 * The runner's static files are served from the content origin with a CSP set by the API (ADR 0170): the page
 * that bundles the code may fetch its wasm file, the page that runs it has no network at all, and neither
 * allows `unsafe-eval` or inline script.
 */
class RunnerContentTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir() . '/ce-runtime-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/runtime/code-runner/1', 0777, true);
        foreach (['runner.html' => '<!doctype html>', 'exec.html' => '<!doctype html>', 'runner.js' => 'x', 'exec.js' => 'x', 'esbuild.js' => 'x', 'esbuild.wasm' => "\0asm", 'secret.txt' => 'no'] as $name => $body) {
            file_put_contents($this->root . '/runtime/code-runner/1/' . $name, $body);
        }
        config([
            'filesystems.disks.code-exercises-runtime.root' => $this->root,
            'ulams_uploads.content_origin' => 'http://coffee.content.localhost',
            'app.url' => 'http://coffee.localhost',
            'app.frontend_url' => 'http://coffee.app.localhost:4321/some/path',
            'ulams.core.security.admin_url' => 'http://coffee.admin.localhost',
        ]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . '/runtime/code-runner/1/*') ?: [] as $file) {
            unlink($file);
        }
        @rmdir($this->root . '/runtime/code-runner/1');
        @rmdir($this->root . '/runtime/code-runner');
        @rmdir($this->root . '/runtime');
        @rmdir($this->root);
        parent::tearDown();
    }

    private function content(string $file)
    {
        return $this->withHeaders(['X-Ulams-Content-Origin' => '1'])->get('/api/content/runtime/code-runner/1/' . $file);
    }

    /** @return string[] the directives of a CSP, each as written */
    private function directives(string $csp): array
    {
        return array_map('trim', explode(';', $csp));
    }

    public function testTheRunnerFilesAreServedToTheContentOriginOnly(): void
    {
        $this->content('runner.html')->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->content('runner.js')->assertOk()->assertHeader('Content-Type', 'text/javascript; charset=UTF-8');
        $this->content('esbuild.wasm')->assertOk()->assertHeader('Content-Type', 'application/wasm');

        $this->withoutHeader('X-Ulams-Content-Origin')->get('/api/content/runtime/code-runner/1/runner.html')->assertNotFound();
        config(['ulams_uploads.content_origin' => null, 'scorm.content_origin' => null]);
        $this->content('runner.html')->assertNotFound();
    }

    public function testNothingOutsideTheListedFilesOrVersionIsServed(): void
    {
        $this->content('secret.txt')->assertNotFound();
        $this->content('../runner.html')->assertNotFound();
        $this->withHeaders(['X-Ulams-Content-Origin' => '1'])->get('/api/content/runtime/code-runner/2/runner.html')->assertNotFound();
        $this->withHeaders(['X-Ulams-Content-Origin' => '1'])->get('/api/content/runtime/other/1/runner.html')->assertNotFound();

        config(['ulams_code_exercises.enabled' => false]);
        $this->content('runner.html')->assertNotFound();
    }

    public function testThePageThatBundlesTheCodeMayFetchItsWasmAndNothingElse(): void
    {
        $csp = $this->content('runner.html')->headers->get('Content-Security-Policy');
        $directives = $this->directives($csp);

        $this->assertContains("default-src 'none'", $directives);
        $this->assertContains("script-src 'self' 'wasm-unsafe-eval'", $directives);
        $this->assertContains("connect-src 'self'", $directives);
        $this->assertContains("form-action 'none'", $directives);
        $this->assertContains("base-uri 'none'", $directives);
        $this->assertContains('sandbox allow-scripts', $directives);
        $this->assertContains('frame-ancestors http://coffee.app.localhost:4321 http://coffee.admin.localhost:*', $directives, 'only the tenant\'s own pages may frame it');
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
        $this->assertStringNotContainsString("'unsafe-inline'", $csp);
        $this->assertStringNotContainsString('*.', $csp);
    }

    public function testThePageThatRunsTheCodeHasNoNetworkAndNoEval(): void
    {
        $csp = $this->content('exec.html')->headers->get('Content-Security-Policy');
        $directives = $this->directives($csp);

        $this->assertContains("default-src 'none'", $directives);
        $this->assertContains("script-src 'self' blob:", $directives);
        // framed by the sandboxed runner, an opaque origin no frame-ancestors source can match: there is none
        $this->assertSame([], array_filter($directives, fn (string $d) => str_starts_with($d, 'frame-ancestors')));
        // no connect-src, img-src, frame-src, font-src or media-src: they all fall back to 'none'
        foreach (['connect-src', 'img-src', 'frame-src', 'font-src', 'media-src', 'style-src', 'prefetch-src'] as $name) {
            $this->assertSame([], array_filter($directives, fn (string $d) => str_starts_with($d, $name . ' ')), "{$name} must stay at the default 'none'");
        }
        $this->assertStringNotContainsString('unsafe-eval', $csp);
        $this->assertStringNotContainsString('unsafe-inline', $csp);
        $this->assertStringNotContainsString('http:', str_replace(['frame-ancestors'], '', implode(';', array_filter($directives, fn ($d) => !str_starts_with($d, 'frame-ancestors') && !str_starts_with($d, 'report-uri')))));
        $this->assertSame(RunnerHeaders::execCsp(), $csp);
    }

    public function testOtherRunnerFilesGetALockedDownPolicyOfTheirOwn(): void
    {
        $csp = $this->content('runner.js')->headers->get('Content-Security-Policy');

        $this->assertSame("default-src 'none'; sandbox", $csp);
    }
}
