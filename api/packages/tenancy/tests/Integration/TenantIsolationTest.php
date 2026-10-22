<?php

namespace Ulams\Tenancy\Tests\Integration;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * End-to-end isolation check against the running docker stack: provisions two real tenants
 * with `ulams:tenant:create` (database, bucket, env file, keys), then talks to them over HTTP
 * through Caddy.
 *
 * Opt-in, because it creates and drops real databases and buckets:
 *
 *   docker compose exec -T -e TENANCY_INTEGRATION=1 api \
 *     vendor/bin/phpunit packages/tenancy/tests/Integration
 *
 * TENANCY_INTEGRATION_URL (default http://caddy) is where requests go; the tenant is chosen
 * with the Host header. TENANCY_INTEGRATION_KEEP=1 keeps the tenants afterwards.
 */
class TenantIsolationTest extends TestCase
{
    private const A = 'isoprobea';
    private const B = 'isoprobeb';

    private static bool $provisioned = false;
    private static Client $http;

    public static function setUpBeforeClass(): void
    {
        if (!getenv('TENANCY_INTEGRATION')) {
            return;
        }

        self::$http = new Client([
            'base_uri' => getenv('TENANCY_INTEGRATION_URL') ?: 'http://caddy',
            'http_errors' => false,
            'timeout' => 60,
        ]);
        foreach ([self::A, self::B] as $slug) {
            self::artisan(['ulams:tenant:create', $slug, '--name=Isolation ' . strtoupper(substr($slug, -1)), '--users=1']);
        }
        self::$provisioned = true;
    }

    public static function tearDownAfterClass(): void
    {
        if (!self::$provisioned || getenv('TENANCY_INTEGRATION_KEEP')) {
            return;
        }
        foreach ([self::A, self::B] as $slug) {
            self::artisan(['ulams:tenant:delete', $slug, '--force']);
        }
    }

    protected function setUp(): void
    {
        if (!getenv('TENANCY_INTEGRATION')) {
            $this->markTestSkipped('Set TENANCY_INTEGRATION=1 to provision real tenants (see class docblock).');
        }
    }

    public function testTenantsAreServedOnTheirOwnHosts(): void
    {
        $this->assertSame('Application Name: Isolation A', (string) $this->request(self::A, 'GET', '/api/name')->getBody());
        $this->assertSame('Application Name: Isolation B', (string) $this->request(self::B, 'GET', '/api/name')->getBody());
        $this->assertSame(404, $this->request('isoprobenope', 'GET', '/api/name')->getStatusCode());
    }

    public function testDataAndTokensDoNotCrossTenants(): void
    {
        $tokenA = $this->login(self::A);
        $tokenB = $this->login(self::B);
        $title = 'Isolation probe ' . bin2hex(random_bytes(4));

        $created = $this->request(self::A, 'POST', '/api/admin/courses', $tokenA, [
            'title' => $title,
            'status' => 'published',
        ]);
        $this->assertContains($created->getStatusCode(), [200, 201], (string) $created->getBody());

        $this->assertContains($title, $this->courseTitles(self::A));
        $this->assertNotContains($title, $this->courseTitles(self::B));

        // every tenant signs tokens with its own Passport keys
        $this->assertSame(200, $this->request(self::A, 'GET', '/api/profile/me', $tokenA)->getStatusCode());
        $this->assertSame(401, $this->request(self::B, 'GET', '/api/profile/me', $tokenA)->getStatusCode());
        $this->assertSame(401, $this->request(self::B, 'GET', '/api/admin/courses', $tokenA)->getStatusCode());
        $this->assertSame(401, $this->request(self::A, 'GET', '/api/profile/me', $tokenB)->getStatusCode());
        $this->assertSame(401, $this->request('api.localhost', 'GET', '/api/profile/me', $tokenA)->getStatusCode());

        // users of one tenant cannot log in to another
        $wrong = $this->request(self::B, 'POST', '/api/auth/login', null, [
            'email' => 'admin@' . self::A . '.ulams.app',
            'password' => getenv('TENANT_DEMO_PASSWORD') ?: 'secret',
        ]);
        $this->assertNotSame(200, $wrong->getStatusCode());
    }

    public function testLearnerInsightsDoNotCrossTenants(): void
    {
        $tokenA = $this->login(self::A);
        $created = $this->request(self::A, 'POST', '/api/admin/courses', $tokenA, ['title' => 'Insights probe ' . bin2hex(random_bytes(4)), 'status' => 'published']);
        $this->assertContains($created->getStatusCode(), [200, 201], (string) $created->getBody());
        $courseId = json_decode((string) $created->getBody(), true)['data']['id'];
        $summary = "/api/admin/learner-insights/courses/{$courseId}/summary";

        // the learner's own view and the aggregates of a course work in tenant A with A's token...
        $this->assertSame(200, $this->request(self::A, 'GET', '/api/learner-insights/me', $tokenA)->getStatusCode());
        $this->assertNotSame(401, $this->request(self::A, 'GET', $summary, $tokenA)->getStatusCode());
        // ...and are refused on tenant B, whose Passport keys differ
        $this->assertSame(401, $this->request(self::B, 'GET', '/api/learner-insights/me', $tokenA)->getStatusCode());
        $this->assertSame(401, $this->request(self::B, 'GET', $summary, $tokenA)->getStatusCode());
    }

    public function testIdentityProvidersAndSignInStateDoNotCrossTenants(): void
    {
        $tokenA = $this->login(self::A);
        $tokenB = $this->login(self::B);
        $slug = 'probe-' . bin2hex(random_bytes(3));

        $created = $this->request(self::A, 'POST', '/api/admin/identity/providers', $tokenA, [
            'slug' => $slug,
            'name' => 'Isolation probe',
            'enabled' => false,
            'oidc' => ['issuer' => 'https://idp.example.com/realms/probe', 'client_id' => 'probe'],
        ]);
        $this->assertSame(201, $created->getStatusCode(), (string) $created->getBody());
        $id = json_decode((string) $created->getBody(), true)['data']['id'];

        // the provider is A's: listed and readable there, unknown at B
        $slugsOf = fn (string $tenant, string $token): array => array_column(json_decode((string) $this->request($tenant, 'GET', '/api/admin/identity/providers', $token)->getBody(), true)['data'] ?? [], 'slug');
        $this->assertContains($slug, $slugsOf(self::A, $tokenA));
        $this->assertNotContains($slug, $slugsOf(self::B, $tokenB));
        $this->assertSame(200, $this->request(self::A, 'GET', "/api/admin/identity/providers/{$id}", $tokenA)->getStatusCode());
        $this->assertSame(404, $this->request(self::B, 'GET', "/api/admin/identity/providers/{$id}", $tokenB)->getStatusCode());
        $this->assertSame(404, $this->request(self::B, 'DELETE', "/api/admin/identity/providers/{$id}", $tokenB)->getStatusCode());

        // every tenant signs tokens with its own keys, so A's admin token is nothing at B
        $this->assertSame(401, $this->request(self::B, 'GET', '/api/admin/identity/providers', $tokenA)->getStatusCode());
        $this->assertSame(401, $this->request(self::B, 'GET', '/api/admin/identity/audit', $tokenA)->getStatusCode());

        // a sign-in cannot start at B for A's provider (and B has no such enabled provider either)
        $noRedirects = fn (string $tenant, string $uri) => self::$http->request('GET', $uri, ['allow_redirects' => false, 'headers' => ['Host' => "{$tenant}.localhost", 'Accept' => 'application/json']]);
        $this->assertSame(404, $noRedirects(self::B, "/api/identity/sso/{$slug}/start")->getStatusCode());
        $this->assertSame(400, $noRedirects(self::B, "/api/identity/oidc/{$slug}/callback?state=" . str_repeat('a', 43) . '.9999999999.' . str_repeat('b', 43))->getStatusCode());

        $this->request(self::A, 'DELETE', "/api/admin/identity/providers/{$id}", $tokenA);
    }

    public function testSocialLoginStateAndCodesDoNotCrossTenants(): void
    {
        $verifier = bin2hex(random_bytes(32));
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $query = http_build_query(['challenge' => $challenge, 'return_url' => 'http://' . self::A . '.app.localhost/auth/social/callback']);
        $noRedirects = fn (string $slug, string $method, string $uri, ?array $json = null) => self::$http->request($method, $uri, [
            'allow_redirects' => false,
            'headers' => ['Host' => "{$slug}.localhost", 'Accept' => 'application/json'],
            'json' => $json,
        ]);

        // tenant A starts a login (the provider's client id may be a placeholder on a test tenant)
        $start = $noRedirects(self::A, 'GET', '/api/auth/social/google?' . $query);
        if ($start->getStatusCode() !== 302) {
            $this->markTestSkipped('Google login is not configured on the test tenant (status ' . $start->getStatusCode() . ').');
        }
        parse_str((string) parse_url($start->getHeaderLine('Location'), PHP_URL_QUERY), $params);
        $this->assertNotEmpty($params['state'] ?? null, 'the redirect to the provider carries our signed state');

        // the state is signed with A's APP_KEY and kept in A's database: B refuses it, and so does the platform host
        $this->assertSame(400, $noRedirects(self::B, 'GET', '/api/auth/social/google/callback?state=' . urlencode($params['state']))->getStatusCode());
        $this->assertSame(400, $noRedirects('api', 'GET', '/api/auth/social/google/callback?state=' . urlencode($params['state']))->getStatusCode());
        // a code that was never issued on B is refused there, whatever tenant it is taken from
        $this->assertSame(400, $noRedirects(self::B, 'POST', '/api/auth/social/exchange', ['code' => $challenge, 'verifier' => $verifier])->getStatusCode());
    }

    public function testLtiKeySetsAndRegistrationsDoNotCrossTenants(): void
    {
        $kids = fn (string $slug) => array_column(json_decode((string) $this->request($slug, 'GET', '/api/lti/jwks')->getBody(), true)['keys'] ?? [], 'kid');
        $a = $kids(self::A);
        $b = $kids(self::B);
        $this->assertNotEmpty($a);
        $this->assertNotEmpty($b);
        $this->assertSame([], array_intersect($a, $b), 'every tenant signs LTI messages with its own keys');

        $tokenA = $this->login(self::A);
        $tool = $this->request(self::A, 'POST', '/api/admin/lti/tools', $tokenA, [
            'name' => 'Isolation tool',
            'oidc_login_url' => 'https://tool.example.test/login',
            'launch_url' => 'https://tool.example.test/launch',
            'jwks_url' => 'https://tool.example.test/jwks',
        ]);
        $this->assertSame(201, $tool->getStatusCode(), (string) $tool->getBody());
        $clientId = json_decode((string) $tool->getBody(), true)['data']['client_id'];

        // tenant B does not know tenant A's tool, and A's admin token is useless on B
        $authorize = $this->request(self::B, 'GET', '/api/lti/platform/authorize?' . http_build_query([
            'scope' => 'openid', 'response_type' => 'id_token', 'client_id' => $clientId,
            'redirect_uri' => 'https://tool.example.test/launch', 'login_hint' => 'x', 'nonce' => 'n',
        ]));
        $this->assertSame(401, $authorize->getStatusCode());
        $this->assertSame(401, $this->request(self::B, 'GET', '/api/admin/lti/tools', $tokenA)->getStatusCode());
    }

    public function testLayoutTopicsDoNotCrossTenants(): void
    {
        $tokenA = $this->login(self::A);
        $tokenB = $this->login(self::B);
        $course = $this->request(self::A, 'POST', '/api/admin/courses', $tokenA, ['title' => 'Layout isolation course', 'status' => 'published']);
        $this->assertContains($course->getStatusCode(), [200, 201], (string) $course->getBody());
        $courseId = json_decode((string) $course->getBody(), true)['data']['id'];
        $lesson = $this->request(self::A, 'POST', '/api/admin/lessons', $tokenA, ['title' => 'Layout isolation lesson', 'course_id' => $courseId, 'active' => true, 'order' => 1]);
        $this->assertContains($lesson->getStatusCode(), [200, 201], (string) $lesson->getBody());
        $lessonId = json_decode((string) $lesson->getBody(), true)['data']['id'];

        $created = $this->request(self::A, 'POST', '/api/admin/topics', $tokenA, [
            'title' => 'Layout isolation probe',
            'lesson_id' => $lessonId,
            'topicable_type' => 'Ulams\\TopicTypeLayout\\Models\\LayoutTopic',
            'document' => [['component' => 'Callout', 'props' => ['text' => 'Only on A.']]],
            'markdown_fallback' => 'Only on A.',
        ]);
        $this->assertSame(201, $created->getStatusCode(), (string) $created->getBody());
        $id = json_decode((string) $created->getBody(), true)['data']['id'];

        $this->assertSame(200, $this->request(self::A, 'GET', "/api/admin/topics/{$id}", $tokenA)->getStatusCode());
        $onB = $this->request(self::B, 'GET', "/api/admin/topics/{$id}", $tokenB);
        $this->assertNotSame(200, $onB->getStatusCode());
        $this->assertStringNotContainsString('Only on A', (string) $onB->getBody());
        $this->assertSame(401, $this->request(self::B, 'GET', "/api/admin/topics/{$id}", $tokenA)->getStatusCode());
        $this->assertSame(401, $this->request(self::B, 'PATCH', "/api/admin/topics/{$id}", $tokenA, ['topicable_type' => 'Ulams\\TopicTypeLayout\\Models\\LayoutTopic', 'markdown_fallback' => 'Changed'])->getStatusCode());
    }

    public function testLiaScriptSourcesDoNotCrossTenants(): void
    {
        $tokenA = $this->login(self::A);
        $tokenB = $this->login(self::B);
        $created = $this->request(self::A, 'POST', '/api/admin/liascript', $tokenA, ['markdown' => "# Isolation probe\n\nOnly on A."]);
        $this->assertSame(201, $created->getStatusCode(), (string) $created->getBody());
        $id = json_decode((string) $created->getBody(), true)['data']['id'];

        $this->assertSame(200, $this->request(self::A, 'GET', "/api/admin/liascript/{$id}/source", $tokenA)->getStatusCode());
        $onB = $this->request(self::B, 'GET', "/api/admin/liascript/{$id}/source", $tokenB);
        $this->assertNotSame(200, $onB->getStatusCode());
        $this->assertStringNotContainsString('Only on A', (string) $onB->getBody());
        $this->assertSame(401, $this->request(self::B, 'GET', "/api/admin/liascript/{$id}/source", $tokenA)->getStatusCode());
    }

    public function testInteractivePackagesDoNotCrossTenants(): void
    {
        $tokenA = $this->login(self::A);
        $tokenB = $this->login(self::B);
        $zipPath = tempnam(sys_get_temp_dir(), 'isolation-') . '.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('index.html', '<p>Only on A</p>');
        $zip->addFromString('ulams-interactive.json', json_encode([
            'id' => 'isolation', 'title' => ['en' => 'Isolation probe'], 'version' => '1.0.0', 'licence' => 'MIT',
            'locales' => ['en'], 'defaultLocale' => 'en', 'bridge' => 1,
            'steps' => [['id' => 'only', 'title' => ['en' => 'Only'], 'text' => ['en' => 'Only on A.']]],
        ]));
        $zip->close();

        $created = $this->request(self::A, 'POST', '/api/admin/interactive', $tokenA, null, [
            ['name' => 'file', 'contents' => fopen($zipPath, 'r'), 'filename' => 'isolation.zip'],
        ]);
        @unlink($zipPath);
        $this->assertSame(201, $created->getStatusCode(), (string) $created->getBody());
        $id = json_decode((string) $created->getBody(), true)['data']['id'];

        $this->assertSame(200, $this->request(self::A, 'GET', "/api/admin/interactive/{$id}", $tokenA)->getStatusCode());
        foreach ([['GET', "/api/admin/interactive/{$id}"], ['GET', "/api/admin/interactive/{$id}/versions"], ['GET', "/api/admin/interactive/{$id}/preview"], ['DELETE', "/api/admin/interactive/{$id}"]] as [$method, $uri]) {
            $onB = $this->request(self::B, $method, $uri, $tokenB);
            $this->assertNotSame(200, $onB->getStatusCode(), "{$method} {$uri}");
            $this->assertStringNotContainsString('Isolation probe', (string) $onB->getBody());
            $this->assertSame(401, $this->request(self::B, $method, $uri, $tokenA)->getStatusCode(), "{$method} {$uri} with A's token");
        }
        $list = $this->request(self::B, 'GET', '/api/admin/interactive', $tokenB);
        $this->assertSame(200, $list->getStatusCode());
        $this->assertStringNotContainsString('Isolation probe', (string) $list->getBody());

        // the learner endpoints: another tenant knows neither the topic nor the token
        foreach ([['POST', '/api/interactive/launches/1'], ['POST', '/api/interactive/topics/1/events']] as [$method, $uri]) {
            $this->assertSame(401, $this->request(self::B, $method, $uri, $tokenA, ['events' => [['type' => 'complete']]])->getStatusCode(), "{$method} {$uri} with A's token");
        }
        // the public showcase never shows another tenant's package: whatever B answers, it is not A's probe
        $showcaseOnB = $this->request(self::B, 'GET', '/api/interactive/showcase', null);
        $this->assertContains($showcaseOnB->getStatusCode(), [200, 404, 503]);
        $this->assertStringNotContainsString('Isolation probe', (string) $showcaseOnB->getBody());

        $this->assertSame(200, $this->request(self::A, 'GET', "/api/admin/interactive/{$id}", $tokenA)->getStatusCode());
        $this->assertSame(200, $this->request(self::A, 'DELETE', "/api/admin/interactive/{$id}", $tokenA)->getStatusCode());
    }

    /**
     * Course Builder sessions, versions and events stay in their tenant: tenant B with its own token
     * cannot read tenant A's session, events or versions, and A's token is not valid on B. (Needs
     * AI_DRIVER=fake or a key on both tenants; with AI disabled every builder endpoint answers 503.)
     */
    public function testCourseBuilderSessionsDoNotCrossTenants(): void
    {
        $tokenA = $this->login(self::A);
        $tokenB = $this->login(self::B);
        $created = $this->request(self::A, 'POST', '/api/admin/course-builder/sessions', $tokenA, ['title' => 'Isolation probe']);
        if ($created->getStatusCode() === 503) {
            $this->markTestSkipped('AI is disabled on the probe tenants');
        }
        $this->assertSame(201, $created->getStatusCode(), (string) $created->getBody());
        $id = json_decode((string) $created->getBody(), true)['data']['session']['id'];

        foreach (["/api/admin/course-builder/sessions/{$id}", "/api/admin/course-builder/sessions/{$id}/versions", "/api/admin/course-builder/sessions/{$id}/events", "/api/admin/course-builder/sessions/{$id}/brief"] as $uri) {
            $this->assertSame(200, $this->request(self::A, 'GET', $uri, $tokenA)->getStatusCode(), $uri);
            $onB = $this->request(self::B, 'GET', $uri, $tokenB);
            $this->assertSame(404, $onB->getStatusCode(), $uri);
            $this->assertStringNotContainsString('Isolation probe', (string) $onB->getBody());
            $this->assertSame(401, $this->request(self::B, 'GET', $uri, $tokenA)->getStatusCode(), $uri);
        }
        $list = json_decode((string) $this->request(self::B, 'GET', '/api/admin/course-builder/sessions', $tokenB)->getBody(), true);
        $this->assertNotContains($id, array_column($list['data'] ?? [], 'id'));
    }

    /**
     * Course-as-code endpoints (ADR 0160) resolve only inside their tenant: another tenant's token gets 404 for
     * A's session and version ids and A's token is rejected on B. Needs a session, so it is skipped with AI off.
     */
    public function testCourseAsCodeEndpointsDoNotCrossTenants(): void
    {
        $tokenA = $this->login(self::A);
        $tokenB = $this->login(self::B);
        $created = $this->request(self::A, 'POST', '/api/admin/course-builder/sessions', $tokenA, ['title' => 'Isolation probe']);
        if ($created->getStatusCode() === 503) {
            $this->markTestSkipped('AI is disabled on the probe tenants');
        }
        $this->assertSame(201, $created->getStatusCode(), (string) $created->getBody());
        $id = json_decode((string) $created->getBody(), true)['data']['session']['id'];
        $versionId = '01aaaaaaaaaaaaaaaaaaaaaaaa';
        $body = ['document' => ['schemaVersion' => 3], 'baseVersionId' => $versionId];

        foreach ([['POST', "/api/admin/course-as-code/sessions/{$id}/imports", $body], ['POST', "/api/admin/course-as-code/sessions/{$id}/apply", []], ['POST', "/api/admin/course-as-code/sessions/{$id}/publish", []], ['POST', "/api/admin/course-as-code/sessions/{$id}/drift", []]] as [$method, $uri, $payload]) {
            $onB = $this->request(self::B, $method, $uri, $tokenB, $payload);
            $this->assertSame(404, $onB->getStatusCode(), "{$method} {$uri}");
            $this->assertStringNotContainsString('Isolation probe', (string) $onB->getBody());
            $this->assertSame(401, $this->request(self::B, $method, $uri, $tokenA, $payload)->getStatusCode(), "{$method} {$uri} with A's token");
        }
        $this->assertSame(401, $this->request(self::B, 'GET', '/api/admin/course-as-code/courses/1/blueprint', $tokenA)->getStatusCode());
        $this->assertSame(404, $this->request(self::B, 'GET', "/api/admin/course-as-code/versions/{$versionId}", $tokenB)->getStatusCode());
        $this->assertSame(401, $this->request(self::B, 'GET', "/api/admin/course-as-code/versions/{$versionId}", $tokenA)->getStatusCode());
    }

    private function login(string $slug): string
    {
        $response = $this->request($slug, 'POST', '/api/auth/login', null, [
            'email' => "admin@{$slug}.ulams.app",
            'password' => getenv('TENANT_DEMO_PASSWORD') ?: 'secret',
        ]);
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        return json_decode((string) $response->getBody(), true)['data']['token'];
    }

    private function courseTitles(string $slug): array
    {
        $response = $this->request($slug, 'GET', '/api/courses?per_page=100');
        $this->assertSame(200, $response->getStatusCode());

        return array_column(json_decode((string) $response->getBody(), true)['data'] ?? [], 'title');
    }

    private function request(string $slugOrHost, string $method, string $uri, ?string $token = null, ?array $json = null, ?array $multipart = null): ResponseInterface
    {
        $host = str_contains($slugOrHost, '.') ? $slugOrHost : "{$slugOrHost}.localhost";
        $options = ['headers' => array_filter([
            'Host' => $host,
            'Accept' => 'application/json',
            'Authorization' => $token ? "Bearer {$token}" : null,
        ])];
        if ($json !== null) {
            $options['json'] = $json;
        }
        if ($multipart !== null) {
            $options['multipart'] = $multipart;
        }

        return self::$http->request($method, $uri, $options);
    }

    /**
     * Runs a platform artisan command. The test process carries test-database variables
     * (phpunit.xml, docker -e), which the child must not inherit.
     */
    private static function artisan(array $arguments): void
    {
        $base = dirname(__DIR__, 4);
        $env = array_fill_keys(array_unique([...array_keys(getenv()), ...array_keys($_ENV)]), false);
        foreach (['PATH', 'HOME', 'TENANT_DEMO_PASSWORD'] as $keep) {
            if (getenv($keep) !== false) {
                $env[$keep] = getenv($keep);
            }
        }

        $process = new Process([PHP_BINARY, $base . '/artisan', ...$arguments, '--no-interaction'], $base, $env, null, 900);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new RuntimeException('artisan ' . implode(' ', $arguments) . " failed:\n" . $process->getOutput() . $process->getErrorOutput());
        }
    }
}
