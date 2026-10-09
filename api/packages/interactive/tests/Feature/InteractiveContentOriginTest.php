<?php

namespace Ulams\Interactive\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Ulams\Interactive\Models\InteractivePackage;
use Ulams\Interactive\Services\Contracts\InteractivePackageServiceContract;
use Ulams\Interactive\Services\InteractiveCsp;
use Ulams\Interactive\Tests\TestCase;

class InteractiveContentOriginTest extends TestCase
{
    private InteractivePackage $package;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.default'));
        config([
            'ulams_uploads.content_origin' => 'http://coffee.content.localhost',
            'app.url' => 'http://coffee.localhost',
            'app.frontend_url' => 'http://coffee.app.localhost:4321/some/path',
            'ulams.core.security.admin_url' => 'http://coffee.admin.localhost',
        ]);
        $this->package = app(InteractivePackageServiceContract::class)->create($this->upload($this->packageZip('steps')), null, null);
    }

    private function content(string $path)
    {
        return $this->withHeaders(['X-Ulams-Content-Origin' => '1'])->get('/api/content/' . $path);
    }

    private function csp(string $path = 'index.html'): string
    {
        return $this->content("interactive/{$this->package->storage_key}/v1/{$path}")->assertOk()->headers->get('Content-Security-Policy');
    }

    public function testFilesAreServedOnlyToTheContentOriginWithTheirOwnCsp(): void
    {
        $key = $this->package->storage_key;
        $this->content("interactive/{$key}/v1/index.html")->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->content("interactive/{$key}/v1/app.js")->assertOk()->assertHeader('Content-Type', 'text/javascript; charset=UTF-8');

        // a browser opening the API URL directly: no proxy header, no package HTML on the API origin
        $this->withoutHeader('X-Ulams-Content-Origin')->get("/api/content/interactive/{$key}/v1/index.html")->assertNotFound();
        config(['ulams_uploads.content_origin' => null, 'scorm.content_origin' => null]);
        $this->content("interactive/{$key}/v1/index.html")->assertNotFound();
    }

    public function testUnknownKeysVersionsAndTraversalAreNotFound(): void
    {
        $key = $this->package->storage_key;
        $this->content("interactive/{$key}/v9/index.html")->assertNotFound();
        $this->content('interactive/00000000-0000-0000-0000-000000000000/v1/index.html')->assertNotFound();
        $this->content("interactive/{$key}/v1/missing.html")->assertNotFound();
        $this->content("interactive/{$key}/v1/../../../etc/passwd")->assertNotFound();
        $this->content('interactive/1/v1/index.html')->assertNotFound();
        $this->content('interactive')->assertNotFound();
    }

    public function testTheCspIsStrictAndNeverAllowsEval(): void
    {
        $csp = $this->csp();

        $this->assertStringNotContainsString('unsafe-eval', $csp);
        $this->assertStringNotContainsString('wasm-unsafe-eval', $csp);
        $directives = collect(explode('; ', $csp))->mapWithKeys(fn ($d) => [explode(' ', $d)[0] => substr($d, strlen(explode(' ', $d)[0]) + 1)]);
        $this->assertSame("'none'", $directives['default-src']);
        $this->assertSame("'self' 'unsafe-inline'", $directives['script-src']);
        $this->assertSame("'self'", $directives['connect-src']);
        $this->assertSame("'none'", $directives['frame-src']);
        $this->assertSame("'none'", $directives['form-action']);
        $this->assertSame("'none'", $directives['base-uri']);
        $this->assertSame("'none'", $directives['object-src']);
        $this->assertSame("'self' blob:", $directives['worker-src']);
        $this->assertSame('http://coffee.localhost/api/csp-report', $directives['report-uri']);
        // the same policy for every file of the version, scripts and images included
        $this->assertSame($csp, $this->csp('app.js'));
        $this->assertSame($csp, $this->csp('posters/intro.webp'));
    }

    public function testOnlyTheTenantsFrontAndAdminMayFrameAPackage(): void
    {
        config(['app.env' => 'production']);
        $this->app['env'] = 'production';
        config(['ulams.core.security.trusted_origins' => ['https://staging.example.com', 'javascript:alert(1)', 'not a url']]);
        $csp = $this->csp();

        $this->assertStringContainsString('frame-ancestors http://coffee.app.localhost:4321 http://coffee.admin.localhost https://staging.example.com;', $csp . ';');
        $this->assertStringNotContainsString('*', $csp);

        config(['app.frontend_url' => 'javascript:alert(1)', 'ulams.core.security.admin_url' => null, 'ulams.core.security.trusted_origins' => []]);
        $this->assertStringContainsString("frame-ancestors 'none'", $this->csp());
    }

    public function testTheDevFrontOnItsOwnPortMayFrameAPackageOutsideProduction(): void
    {
        // the dev front runs on :4321 while the tenant env says http://coffee.app.localhost
        config(['app.frontend_url' => 'http://coffee.app.localhost', 'ulams.core.security.admin_url' => 'http://coffee.admin.localhost', 'ulams.core.security.trust_localhost_outside_production' => true]);
        $this->assertStringContainsString('frame-ancestors http://coffee.app.localhost:* http://coffee.admin.localhost:*;', $this->csp() . ';');

        // a real host never gets a wildcard port, and neither does production
        config(['app.frontend_url' => 'https://acme.ulams.app']);
        $this->assertStringContainsString('frame-ancestors https://acme.ulams.app http://coffee.admin.localhost:*;', $this->csp() . ';');
        $this->app['env'] = 'production';
        config(['app.frontend_url' => 'http://coffee.app.localhost']);
        $this->assertStringContainsString('frame-ancestors http://coffee.app.localhost http://coffee.admin.localhost;', $this->csp() . ';');
    }

    public function testTheNetworkAllowListNeedsBothTheTenantSettingAndTheManifest(): void
    {
        // the manifest lists https://tiles.example.com
        $this->assertStringContainsString("connect-src 'self';", $this->csp() . ';');
        $this->assertStringNotContainsString('tiles.example.com', $this->csp());

        config(['ulams_interactive.allow_network' => true]);
        $this->assertStringContainsString("connect-src 'self' https://tiles.example.com;", $this->csp());

        // a package that lists nothing gets nothing, whatever the tenant allows
        $minimal = app(InteractivePackageServiceContract::class)->create($this->upload($this->packageZip('minimal')), null, null);
        $response = $this->content("interactive/{$minimal->storage_key}/v1/index.html")->assertOk();
        $this->assertStringContainsString("connect-src 'self';", $response->headers->get('Content-Security-Policy') . ';');
    }

    public function testTheCspIsPerVersion(): void
    {
        app(InteractivePackageServiceContract::class)->addVersion($this->package, $this->upload($this->packageZip('steps', ['network' => []])), null);

        config(['ulams_interactive.allow_network' => true]);
        $this->assertStringContainsString('tiles.example.com', $this->csp());
        $v2 = $this->content("interactive/{$this->package->storage_key}/v2/index.html")->assertOk()->headers->get('Content-Security-Policy');
        $this->assertStringNotContainsString('tiles.example.com', $v2);
    }

    public function testTheBuilderIgnoresAnythingButWellFormedOrigins(): void
    {
        $version = $this->package->version(1);
        $manifest = $version->manifest;
        $manifest['network'] = ["https://ok.example.com:8443", "https://x.com; script-src *", "http://plain.example.com"];
        $version->manifest = $manifest;
        config(['ulams_interactive.allow_network' => true]);

        $csp = InteractiveCsp::for($version);
        $this->assertStringContainsString("connect-src 'self' https://ok.example.com:8443;", $csp . ';');
        $this->assertStringNotContainsString('script-src *', $csp);
        $this->assertStringNotContainsString('plain.example.com', $csp);
    }

    public function testOtherPackageTypesKeepTheProxyCspSetByNobodyHere(): void
    {
        config(['ulams_uploads.content_disks' => ['scorm' => 'test.disk'], 'test.disk' => config('filesystems.default')]);
        Storage::disk(config('filesystems.default'))->put('scorm/a/index.html', 'x');
        $this->content('scorm/a/index.html')->assertOk();
        $this->assertNull($this->content('scorm/a/index.html')->headers->get('Content-Security-Policy'));
    }
}
