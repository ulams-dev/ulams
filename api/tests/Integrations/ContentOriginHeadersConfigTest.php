<?php

namespace Tests\Integrations;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Static checks of the proxy configuration for the content origin (docs/content-origin.md,
 * "Same-site content origin"). The live behaviour is covered by the opt-in
 * packages/scorm/tests/Integration/ContentOriginHeadersTest.php against Caddy.
 */
class ContentOriginHeadersConfigTest extends TestCase
{
    public static function caddyfiles(): array
    {
        return [
            'development' => [__DIR__ . '/../../docker/conf/Caddyfile'],
            'production example' => [__DIR__ . '/../../../front/docs-site/examples/production/Caddyfile'],
        ];
    }

    private function read(string $path): string
    {
        if (!is_file($path)) {
            $this->markTestSkipped("{$path} is outside the mounted api/ directory");
        }

        return file_get_contents($path);
    }

    /** The body of the `(content_origin) { ... }` snippet. */
    private function snippet(string $caddyfile): string
    {
        $this->assertSame(1, preg_match('/^\(content_origin\) \{\n(.*?)^\}$/ms', $caddyfile, $m));

        return $m[1];
    }

    #[DataProvider('caddyfiles')]
    public function testEveryContentOriginExclusionUsesTheSameCleanRegexp(string $path): void
    {
        // a stray quote at the end of the pattern (it was in the preflight matcher) never matches an Origin,
        // so the content origins were not excluded
        $caddyfile = $this->read($path);
        $count = preg_match_all('/not header_regexp Origin (\S+)\s*$/m', $caddyfile, $m);
        $this->assertGreaterThanOrEqual(2, $count);
        $this->assertSame($count, substr_count($caddyfile, 'not header_regexp Origin'), 'a pattern with trailing characters was not matched');
        foreach ($m[1] as $pattern) {
            $this->assertSame('^(null|https?://([^/:]+\.)*content\.)', $pattern);
        }
    }

    #[DataProvider('caddyfiles')]
    public function testTheContentOriginKeepsItsCspAndAddsIsolationHeaders(string $path): void
    {
        $snippet = $this->snippet($this->read($path));

        $this->assertStringContainsString('Content-Security-Policy "default-src \'self\'', $snippet);
        $this->assertStringContainsString("frame-ancestors 'self' {args[2]}", $snippet);
        $this->assertMatchesRegularExpression('/>X-Content-Type-Options nosniff/', $snippet);
        $this->assertMatchesRegularExpression('/>Cross-Origin-Opener-Policy same-origin/', $snippet);
        $this->assertMatchesRegularExpression('/>Cross-Origin-Resource-Policy cross-origin/', $snippet);
        $this->assertMatchesRegularExpression('/>Access-Control-Allow-Origin \*/', $snippet);
        $this->assertStringContainsString('request_header -Cookie', $snippet);
        $this->assertStringContainsString('header_down -Set-Cookie', $snippet);
    }

    #[DataProvider('caddyfiles')]
    public function testInteractivePackagesGetTheirCspFromTheApiAndTheOtherPrefixesKeepTheProxyOne(string $path): void
    {
        $caddyfile = $this->read($path);
        $snippet = $this->snippet($caddyfile);

        // `?` sets the generic policy only when the upstream sent none: ContentFileController sets the
        // per-version CSP of /interactive/* itself (ADR 0086); every other prefix is unchanged
        $this->assertMatchesRegularExpression('/^\s*\?Content-Security-Policy "default-src \'self\'; script-src \'self\' \'unsafe-inline\' \'unsafe-eval\';/m', $snippet);
        $this->assertDoesNotMatchRegularExpression('/^\s*Content-Security-Policy /m', $snippet);
        $this->assertSame(1, preg_match('/path (\/scorm\/\*[^\n]*)\n/', $snippet, $m));
        $this->assertSame('/scorm/* /cmi5/* /adapt/* /liascript/* /interactive/*', trim($m[1]));

        // package uploads of up to UPLOADS_INTERACTIVE_MAX_MB, plus multipart overhead
        $this->assertSame(1, preg_match('/@large_uploads path (.*)\n/', $caddyfile, $m));
        $this->assertStringContainsString('/api/admin/interactive /api/admin/interactive/*/versions', $m[1]);
    }

    #[DataProvider('caddyfiles')]
    public function testNoSiteReflectsAContentOrigin(string $path): void
    {
        $caddyfile = $this->read($path);

        // reflecting the request Origin is only allowed behind a matcher that excludes `null` and
        // content origins; the old bare `header Origin {http.request.header.Origin}` is gone
        $this->assertDoesNotMatchRegularExpression('/@origin\w*\s+header Origin \{http\.request\.header\.Origin\}/', $caddyfile);
        preg_match_all('/@(origin\w*) \{(.*?)\}/s', $caddyfile, $matchers, PREG_SET_ORDER);
        $this->assertNotEmpty($matchers);
        foreach ($matchers as $matcher) {
            $this->assertStringContainsString('not header_regexp Origin ^(null|https?://([^/:]+\.)*content\.)', $matcher[2], "@{$matcher[1]}");
        }
        // credentials only on those reflected answers
        $this->assertDoesNotMatchRegularExpression('/^\s*header Access-Control-Allow-Credentials/m', $caddyfile);
    }

    #[DataProvider('caddyfiles')]
    public function testOnlyTheTrackingEndpointsAreOpenToContentOriginsAndTakeNoCookies(string $path): void
    {
        $caddyfile = $this->read($path);

        $this->assertSame(1, preg_match('/@tracking path (.*)\n/', $caddyfile, $m));
        $this->assertSame('/api/scorm/content/* /api/liascript/progress/*', trim($m[1]));
        $this->assertSame(1, preg_match('/handle @tracking \{\n\s*request_header -Cookie\n\s*request_header -Authorization/', $caddyfile));
    }

    #[DataProvider('caddyfiles')]
    public function testCmi5AusMayCallOnlyTheLrsAndTheFetchEndpointWithoutCookies(string $path): void
    {
        $caddyfile = $this->read($path);

        $this->assertSame(1, preg_match('/@cmi5 path (.*)\n/', $caddyfile, $m));
        // a `*` does not cross a `/`: statements are one segment, state and profiles are two
        $this->assertSame('/api/cmi5/fetch /trax/api/*/xapi/std/* /trax/api/*/xapi/std/*/*', trim($m[1]));
        $this->assertSame(1, preg_match('/handle @cmi5 \{\n\s*request_header -Cookie\n\s*header Access-Control-Allow-Origin \*\n/', $caddyfile));
        // the session token travels in Authorization, so it is not dropped here, and the preflight
        // names the header explicitly (a wildcard does not cover it)
        $start = (int) strpos($caddyfile, 'handle @cmi5 {');
        $block = substr($caddyfile, $start, (int) strpos($caddyfile, '@csp_report path', $start) - $start);
        $this->assertStringNotContainsString('request_header -Authorization', $block);
        $this->assertMatchesRegularExpression('/Access-Control-Allow-Headers "[^"]*Authorization[^"]*X-Experience-API-Version/', $caddyfile);
    }

    #[DataProvider('caddyfiles')]
    public function testCspViolationsAreReportedToTheTenantApiAndTheCollectorTakesNoCookies(string $path): void
    {
        $caddyfile = $this->read($path);

        // content origin: its own CSP reports to the tenant API, which is the 2nd argument
        $snippet = $this->snippet($caddyfile);
        $this->assertStringContainsString('report-uri {args[1]}/api/csp-report; report-to csp-endpoint', $snippet);
        $this->assertStringContainsString('Reporting-Endpoints `csp-endpoint="{args[1]}/api/csp-report"`', $snippet);

        // the collector answers any origin, without cookies or credentials, and nothing else is opened
        $this->assertSame(1, preg_match('/@csp_report path (.*)\n/', $caddyfile, $m));
        $this->assertSame('/api/csp-report', trim($m[1]));
        $this->assertSame(1, preg_match('/handle @csp_report \{\n\s*request_header -Cookie\n\s*request_header -Authorization\n\s*header Access-Control-Allow-Origin \*\n/', $caddyfile));
    }

    #[DataProvider('caddyfiles')]
    public function testTheAdminHasItsOwnPolicyAndTheFrontSetsItsOwnInsteadOfTheProxy(string $path): void
    {
        $caddyfile = $this->read($path);

        $this->assertSame(1, preg_match('/^\(app_csp_admin\) \{\n(.*?)^\}$/ms', $caddyfile, $m));
        $admin = $m[1];
        $this->assertStringContainsString("frame-src 'self'", $admin);
        $this->assertMatchesRegularExpression('/frame-src [^;]* https:;/', $admin);
        $this->assertStringContainsString('report-uri {args[0]}/api/csp-report; report-to csp-endpoint', $admin);
        $this->assertStringContainsString('Reporting-Endpoints', $admin);
        $this->assertStringNotContainsString('app_csp_report_only', $caddyfile);
        // the learner front's CSP comes from front/web/src/middleware.ts: no import in its site block
        $this->assertSame(1, preg_match('/(?:^|\n)(?:http:\/\/\*\.app\.localhost, http:\/\/app\.localhost|\(web\)) \{\n(.*?)\n\}/s', $caddyfile, $web));
        $this->assertStringNotContainsString('app_csp', $web[1]);
        $this->assertStringContainsString('front/web/src/middleware.ts', $web[1]);
    }

    #[DataProvider('caddyfiles')]
    public function testTheFrontsRefuseNoCorsEmbeddingOfTheirJson(string $path): void
    {
        $caddyfile = $this->read($path);

        $this->assertStringContainsString('header /bff/* Cross-Origin-Resource-Policy same-origin', $caddyfile);
        $this->assertStringContainsString('header /studio/api/* Cross-Origin-Resource-Policy same-origin', $caddyfile);
    }

    public function testNoCorsAllowListContainsAContentOrigin(): void
    {
        $cors = config('cors');
        $listed = array_merge((array) $cors['allowed_origins'], (array) $cors['allowed_origins_patterns']);

        foreach ($listed as $entry) {
            $this->assertDoesNotMatchRegularExpression('/(^|[.\/])content\./', (string) $entry, 'CORS allow-list names a content origin');
        }
        // credentials are never allowed together with the wildcard
        $this->assertFalse($cors['supports_credentials']);
    }

    public function testTheTenantEnvFileNeverFeedsTheCorsAllowListsWithTheContentOrigin(): void
    {
        // the H5P service derives its CORS list from FRONTEND_URL and ADMIN_URL only (and drops
        // content origins, api/h5p/test/cors-content-origin.test.ts); the API reads the origins
        // above from config. Check that the keys written to the tenant env file keep it that way.
        $source = file_get_contents(__DIR__ . '/../../packages/tenancy/src/Support/TenantNaming.php');
        $this->assertDoesNotMatchRegularExpression('/(CORS|TRUSTED_ORIGINS)[A-Z_]*.{0,40}content_host/s', $source);
    }
}
