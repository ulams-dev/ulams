<?php

namespace Ulams\Lti\Tests\Feature;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Spatie\Permission\Models\Role;
use Ulams\Courses\Models\Course;
use Ulams\Lti\Models\LtiPlatform;
use Ulams\Lti\Models\LtiUserLink;
use Ulams\Lti\Support\Lti;
use Ulams\Lti\Tests\Support\KeyPair;
use Ulams\Lti\Tests\TestCase;
use Ulams\Lti\Tool\ToolLaunchService;

/**
 * Tool side, LTI Client Side postMessage Storage (`lti_storage_target`): the login page puts the nonce in
 * the platform's storage frame, the launch reads it back and posts it to /api/lti/tool/launch/verify. The
 * server-side state stays the source of truth. The browser half (pages talking to a platform frame) is
 * run by .github/conformance/lti/client-side-storage.cjs.
 */
class ToolClientSideStorageTest extends TestCase
{
    private KeyPair $platformKeys;
    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['student', 'tutor'] as $role) {
            Role::findOrCreate($role, 'api');
        }
        $this->platformKeys = new KeyPair();
        LtiPlatform::factory()->create([
            'issuer' => 'https://moodle.example.test',
            'client_id' => 'moodle-client',
            'deployment_ids' => ['dep-1'],
            'jwks_url' => 'https://moodle.example.test/mod/lti/certs.php',
            'auth_token_url' => 'https://moodle.example.test/mod/lti/token.php',
        ]);
        $this->course = Course::factory()->create(['status' => 'published', 'title' => 'Intro to Git']);
        app(ToolLaunchService::class)->setHttpClient(new Client(['handler' => fn (RequestInterface $r) => Create::promiseFor(
            new Response(200, ['Content-Type' => 'application/json'], json_encode($this->platformKeys->jwks()))
        )]));
    }

    public function testALoginWithAStorageTargetRendersThePageThatPutsTheNonceInThePlatformStorage(): void
    {
        $response = $this->get('/api/lti/tool/login?' . $this->loginQuery(['lti_storage_target' => '_parent']))->assertOk();

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $config = $this->pageConfig($response->getContent());
        $this->assertSame('_parent', $config['target']);
        $this->assertSame('https://moodle.example.test', $config['origin']);

        $next = $this->nextUrl($response->getContent());
        $this->assertStringStartsWith('https://moodle.example.test/mod/lti/auth.php?', $next);
        parse_str(parse_url($next, PHP_URL_QUERY), $auth);
        $this->assertSame('lti1p3_' . $auth['state'], $config['key']);
        $this->assertSame($auth['nonce'], $config['value']);
        $this->assertDatabaseHas('lti_nonces', ['type' => 'storage']);
    }

    public function testWithoutAStorageTargetTheLoginRedirectsAndTheLaunchNeedsNoSecondStep(): void
    {
        $response = $this->get('/api/lti/tool/login?' . $this->loginQuery())->assertRedirect();
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $auth);
        $this->assertDatabaseMissing('lti_nonces', ['type' => 'storage']);

        $this->post('/api/lti/tool/launch', ['id_token' => $this->idToken($auth['nonce']), 'state' => $auth['state']])->assertRedirect();
    }

    public function testAnInvalidStorageTargetFallsBackToTheRedirect(): void
    {
        $this->get('/api/lti/tool/login?' . $this->loginQuery(['lti_storage_target' => '"><script>alert(1)</script>']))->assertRedirect();
        $this->assertDatabaseMissing('lti_nonces', ['type' => 'storage']);
    }

    public function testTheLaunchAfterAStorageLoginFirstReadsTheValueBack(): void
    {
        [$state, $nonce] = $this->storageLogin();

        $page = $this->post('/api/lti/tool/launch', ['id_token' => $this->idToken($nonce), 'state' => $state])->assertOk();

        $this->assertStringContainsString('action="https://lms.example.test/api/lti/tool/launch/verify"', $page->getContent());
        $this->assertStringContainsString('name="state" value="' . $state . '"', $page->getContent());
        $config = $this->pageConfig($page->getContent());
        $this->assertSame('lti1p3_' . $state, $config['key']);
        $this->assertArrayNotHasKey('value', $config, 'the nonce is not handed out again');
        $this->assertSame(0, LtiUserLink::query()->count(), 'nobody is signed in before the value is verified');
    }

    public function testTheValueFromThePlatformStorageCompletesTheLaunchOnce(): void
    {
        [$state, $nonce] = $this->storageLogin();
        $token = $this->idToken($nonce);

        $response = $this->post('/api/lti/tool/launch/verify', ['id_token' => $token, 'state' => $state, 'stored' => $nonce]);

        $response->assertRedirect();
        $this->assertStringStartsWith('https://app.example.test/lti/launch?code=', $response->headers->get('Location'));
        $this->assertSame(1, LtiUserLink::query()->where('sub', 'moodle-7')->count());
        // single use
        $this->post('/api/lti/tool/launch/verify', ['id_token' => $token, 'state' => $state, 'stored' => $nonce])->assertUnauthorized();
    }

    public function testTheVerifyStepIsNotBlockedByTheOriginCheck(): void
    {
        // the storage page sends no referrer, so the browser posts it with `Origin: null`, like the platform's own form post
        [$state, $nonce] = $this->storageLogin();

        $this->withHeaders(['Origin' => 'null'])
            ->post('/api/lti/tool/launch/verify', ['id_token' => $this->idToken($nonce), 'state' => $state, 'stored' => $nonce])
            ->assertRedirect();
    }

    public function testAValueThatIsNotTheNonceOfThisLoginIsRefused(): void
    {
        [$state, $nonce] = $this->storageLogin();

        $this->post('/api/lti/tool/launch/verify', ['id_token' => $this->idToken($nonce), 'state' => $state, 'stored' => 'nonce-of-another-login'])
            ->assertUnauthorized();

        $this->assertSame(0, LtiUserLink::query()->count());
        $this->assertDatabaseHas('lti_launches', ['direction' => 'tool', 'status' => 'failed']);
        // the login is spent: the same state cannot be tried again with the right value
        $this->post('/api/lti/tool/launch/verify', ['id_token' => $this->idToken($nonce), 'state' => $state, 'stored' => $nonce])->assertUnauthorized();
    }

    public function testAPlatformWhoseStorageAnswersNothingFallsBackToTheServerSideState(): void
    {
        [$state, $nonce] = $this->storageLogin();

        $this->post('/api/lti/tool/launch/verify', ['id_token' => $this->idToken($nonce), 'state' => $state, 'stored' => ''])->assertRedirect();
        $this->assertSame(1, LtiUserLink::query()->where('sub', 'moodle-7')->count());
    }

    public function testTheServerSideStateStillDecidesAfterTheStorageStep(): void
    {
        [$state, $nonce] = $this->storageLogin();

        // right stored value, but an id_token for a nonce this login never issued
        $this->post('/api/lti/tool/launch/verify', ['id_token' => $this->idToken('nonce-never-issued'), 'state' => $state, 'stored' => $nonce])
            ->assertUnauthorized();
        $this->assertSame(0, LtiUserLink::query()->count());
    }

    public function testVerifyWithoutALoginOfThisTenantIsRefused(): void
    {
        // a state that exists in no tenant database row (another tenant's login, or a guess)
        $this->post('/api/lti/tool/launch/verify', ['id_token' => $this->idToken('n'), 'state' => 'state-' . str_repeat('a', 64), 'stored' => 'x'])
            ->assertUnauthorized();
        $this->post('/api/lti/tool/launch/verify', [])->assertUnauthorized();
    }

    public function testDeepLinkingGoesThroughTheSameSteps(): void
    {
        [$state, $nonce] = $this->storageLogin();
        $token = $this->idToken($nonce, [
            Lti::CLAIM_MESSAGE_TYPE => Lti::MSG_DEEP_LINKING_REQUEST,
            Lti::CLAIM_ROLES => [Lti::ROLE_INSTRUCTOR],
            Lti::CLAIM_DL_SETTINGS => [
                'deep_link_return_url' => 'https://moodle.example.test/mod/lti/contentitem_return.php',
                'accept_types' => ['ltiResourceLink'],
                'accept_presentation_document_targets' => ['iframe'],
            ],
        ]);

        $this->post('/api/lti/tool/launch', ['id_token' => $token, 'state' => $state])->assertOk()->assertSee('lti-verify', false);
        $this->post('/api/lti/tool/launch/verify', ['id_token' => $token, 'state' => $state, 'stored' => $nonce])
            ->assertOk()->assertSee('Intro to Git');
    }

    /** @return array{0: string, 1: string} state and nonce of a login that used the storage */
    private function storageLogin(): array
    {
        $response = $this->get('/api/lti/tool/login?' . $this->loginQuery(['lti_storage_target' => 'lti-storage']))->assertOk();
        parse_str(parse_url($this->nextUrl($response->getContent()), PHP_URL_QUERY), $auth);

        return [$auth['state'], $auth['nonce']];
    }

    private function loginQuery(array $extra = []): string
    {
        return http_build_query($extra + [
            'iss' => 'https://moodle.example.test',
            'login_hint' => 'moodle-7',
            'target_link_uri' => 'https://lms.example.test/api/lti/tool/launch',
            'client_id' => 'moodle-client',
            'lti_message_hint' => 'abc',
        ]);
    }

    private function pageConfig(string $html): array
    {
        $this->assertSame(1, preg_match('#<script id="lti-storage-config" type="application/json">(.*?)</script>#s', $html, $m));

        return json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);
    }

    /** the platform URL the storage page continues to (a JSON string in its script) */
    private function nextUrl(string $html): string
    {
        $this->assertSame(1, preg_match('#var next = (".*?");#', $html, $m));

        return json_decode(str_replace(['&', '\/'], ['&', '/'], $m[1]), true, 512, JSON_THROW_ON_ERROR);
    }

    private function idToken(string $nonce, array $override = []): string
    {
        return $this->platformKeys->sign(array_merge([
            'iss' => 'https://moodle.example.test',
            'aud' => 'moodle-client',
            'sub' => 'moodle-7',
            'iat' => time(),
            'exp' => time() + 300,
            'nonce' => $nonce,
            Lti::CLAIM_DEPLOYMENT_ID => 'dep-1',
            Lti::CLAIM_MESSAGE_TYPE => Lti::MSG_RESOURCE_LINK,
            Lti::CLAIM_VERSION => '1.3.0',
            Lti::CLAIM_ROLES => [Lti::ROLE_LEARNER],
            Lti::CLAIM_RESOURCE_LINK => ['id' => 'moodle-link-1'],
            Lti::CLAIM_TARGET_LINK_URI => 'https://lms.example.test/api/lti/tool/launch',
            Lti::CLAIM_CUSTOM => ['course_id' => (string) $this->course->getKey()],
        ], $override));
    }
}
