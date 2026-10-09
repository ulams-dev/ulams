<?php

namespace Ulams\Core\Tests\Features;

use Illuminate\Support\Facades\Route;
use Ulams\Core\Tests\TestCase;

class EnforceTrustedOriginTest extends TestCase
{
    private const FRONT = 'https://coffee.ulams.app';
    private const ADMIN = 'https://coffee.admin.ulams.app';
    private const CONTENT = 'https://coffee.content.ulams.app';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.url' => 'https://coffee.api.ulams.app',
            'app.frontend_url' => self::FRONT . '/email',
            'ulams.core.security.admin_url' => self::ADMIN,
            'ulams_uploads.content_origin' => self::CONTENT,
        ]);
        $this->app['env'] = 'production';

        foreach (['api/test/write', 'api/admin/course-builder/sessions', 'api/admin/files/upload',
            'api/scorm/content/abc/track', 'api/liascript/progress/5', 'api/cmi5/fetch', 'api/lti/tool/launch',
            'api/payments-gateways/webhook/stripe'] as $uri) {
            Route::any($uri, fn () => response()->json(['ok' => true]));
        }
    }

    public function testContentOriginCannotWriteToStudioUploadsOrAnyOtherEndpoint(): void
    {
        foreach (['api/admin/course-builder/sessions', 'api/admin/files/upload', 'api/test/write'] as $uri) {
            foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
                $this->call($method, $uri, [], [], [], ['HTTP_ORIGIN' => self::CONTENT])->assertForbidden();
            }
        }
    }

    public function testContentOriginIsRefusedEvenWhenListedAsTrusted(): void
    {
        config(['ulams.core.security.trusted_origins' => [self::CONTENT, 'https://other.content.ulams.app']]);

        $this->call('POST', 'api/test/write', [], [], [], ['HTTP_ORIGIN' => self::CONTENT])->assertForbidden();
        $this->call('POST', 'api/test/write', [], [], [], ['HTTP_ORIGIN' => 'https://other.content.ulams.app'])->assertForbidden();
    }

    public function testOwnAppOriginsPass(): void
    {
        foreach ([self::FRONT, self::ADMIN, 'https://coffee.api.ulams.app'] as $origin) {
            $this->call('POST', 'api/test/write', [], [], [], ['HTTP_ORIGIN' => $origin])->assertOk();
        }
    }

    public function testAnotherTenantsOriginsAreRefused(): void
    {
        $this->call('POST', 'api/test/write', [], [], [], ['HTTP_ORIGIN' => 'https://tea.ulams.app'])->assertForbidden();
        $this->call('POST', 'api/test/write', [], [], [], ['HTTP_ORIGIN' => 'https://tea.admin.ulams.app'])->assertForbidden();
        // exact match: same host on another scheme or port, or as a suffix trick
        $this->call('POST', 'api/test/write', [], [], [], ['HTTP_ORIGIN' => 'http://coffee.ulams.app'])->assertForbidden();
        $this->call('POST', 'api/test/write', [], [], [], ['HTTP_ORIGIN' => 'https://coffee.ulams.app:8443'])->assertForbidden();
        $this->call('POST', 'api/test/write', [], [], [], ['HTTP_ORIGIN' => 'https://coffee.ulams.app.evil.example'])->assertForbidden();
    }

    public function testExtraTrustedOriginsAreConfigurable(): void
    {
        config(['ulams.core.security.trusted_origins' => ['https://partner.example']]);

        $this->call('POST', 'api/test/write', [], [], [], ['HTTP_ORIGIN' => 'https://partner.example'])->assertOk();
    }

    public function testLocalhostIsTrustedOutsideProductionOnly(): void
    {
        $this->call('POST', 'api/test/write', [], [], [], ['HTTP_ORIGIN' => 'http://localhost:3000'])->assertForbidden();

        $this->app['env'] = 'local';
        $this->call('POST', 'api/test/write', [], [], [], ['HTTP_ORIGIN' => 'http://localhost:3000'])->assertOk();
        $this->call('POST', 'api/test/write', [], [], [], ['HTTP_ORIGIN' => 'http://coffee.app.localhost:4321'])->assertOk();
        // the development content origin is *.localhost too, and still refused
        config(['ulams_uploads.content_origin' => 'http://coffee.content.localhost']);
        $this->call('POST', 'api/test/write', [], [], [], ['HTTP_ORIGIN' => 'http://coffee.content.localhost'])->assertForbidden();
        $this->call('POST', 'api/test/write', [], [], [], ['HTTP_ORIGIN' => 'http://tea.content.localhost'])->assertForbidden();
    }

    public function testNonBrowserClientsWithoutOriginPass(): void
    {
        $this->call('POST', 'api/test/write')->assertOk();
        $this->call('POST', 'api/test/write', [], [], [], ['HTTP_SEC_FETCH_SITE' => 'same-origin'])->assertOk();
    }

    public function testWithoutOriginACrossSiteFetchMetadataIsRefused(): void
    {
        foreach (['same-site', 'cross-site'] as $site) {
            $this->call('POST', 'api/test/write', [], [], [], ['HTTP_SEC_FETCH_SITE' => $site])->assertForbidden();
        }
    }

    public function testOriginNullIsRefusedExceptOnTheExemptEndpoints(): void
    {
        $this->call('POST', 'api/test/write', [], [], [], ['HTTP_ORIGIN' => 'null'])->assertForbidden();
        $this->call('POST', 'api/admin/files/upload', [], [], [], ['HTTP_ORIGIN' => 'null'])->assertForbidden();

        // the sandboxed players authenticate with their scoped tracking token, not with cookies
        foreach (['api/scorm/content/abc/track', 'api/liascript/progress/5', 'api/cmi5/fetch', 'api/lti/tool/launch', 'api/payments-gateways/webhook/stripe'] as $uri) {
            $this->call('POST', $uri, [], [], [], ['HTTP_ORIGIN' => 'null'])->assertOk();
            $this->call('POST', $uri, [], [], [], ['HTTP_ORIGIN' => self::CONTENT])->assertOk();
        }
    }

    public function testReadsAreNotChecked(): void
    {
        $this->call('GET', 'api/test/write', [], [], [], ['HTTP_ORIGIN' => self::CONTENT])->assertOk();
    }

    public function testCheckCanBeSwitchedOff(): void
    {
        config(['ulams.core.security.origin_check' => false]);

        $this->call('POST', 'api/test/write', [], [], [], ['HTTP_ORIGIN' => self::CONTENT])->assertOk();
    }
}
