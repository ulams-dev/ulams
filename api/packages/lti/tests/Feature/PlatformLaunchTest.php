<?php

namespace Ulams\Lti\Tests\Feature;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Testing\TestResponse;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Lti\Models\LtiLaunch;
use Ulams\Lti\Models\LtiTool;
use Ulams\Lti\Support\Lti;
use Ulams\Lti\Tests\Support\KeyPair;
use Ulams\Lti\Tests\TestCase;

class PlatformLaunchTest extends TestCase
{
    use CreatesUsers;

    private KeyPair $toolKeys;
    private LtiTool $tool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->toolKeys = new KeyPair();
        $this->tool = LtiTool::factory()->create(['public_key' => $this->toolKeys->public, 'jwks_url' => null]);
    }

    public function testALearnerLaunchesATopicAndTheToolGetsASignedResourceLinkRequest(): void
    {
        [$course, , $topic] = $this->courseWithLink($this->tool, ['custom' => ['level' => 'easy']]);
        $student = $this->makeStudent();
        $this->enrol($student, $course);

        $launch = $this->actingAs($student, 'api')->postJson("/api/lti/launches/{$topic->getKey()}")->assertOk();
        $loginUrl = $launch->json('data.url');
        $this->assertStringStartsWith('https://tool.example.test/lti/login?', $loginUrl);
        parse_str(parse_url($loginUrl, PHP_URL_QUERY), $login);
        $this->assertSame('https://lms.example.test', $login['iss']);
        $this->assertSame($this->tool->client_id, $login['client_id']);
        $this->assertSame('https://tool.example.test/lti/launch', $login['target_link_uri']);

        $response = $this->authorizeRequest($login);
        $response->assertOk();
        $this->assertStringContainsString('action="https://tool.example.test/lti/launch"', $response->getContent());
        $this->assertStringContainsString('name="state" value="tool-state"', $response->getContent());

        $claims = $this->idToken($response);
        $this->assertSame(Lti::MSG_RESOURCE_LINK, $claims[Lti::CLAIM_MESSAGE_TYPE]);
        $this->assertSame('1.3.0', $claims[Lti::CLAIM_VERSION]);
        $this->assertSame($this->tool->client_id, $claims['aud']);
        $this->assertSame('n-1', $claims['nonce']);
        $this->assertSame((string) $student->getKey(), $claims['sub']);
        $this->assertSame($this->tool->deployment_id, $claims[Lti::CLAIM_DEPLOYMENT_ID]);
        $this->assertSame([Lti::ROLE_LEARNER], $claims[Lti::CLAIM_ROLES]);
        $this->assertSame('topic-' . $topic->getKey(), $claims[Lti::CLAIM_RESOURCE_LINK]['id']);
        $this->assertSame('course-' . $course->getKey(), $claims[Lti::CLAIM_CONTEXT]['id']);
        $this->assertSame(['level' => 'easy'], $claims[Lti::CLAIM_CUSTOM]);
        $this->assertContains(Lti::SCOPE_SCORE, $claims[Lti::CLAIM_AGS]['scope']);
        $this->assertStringStartsWith("https://lms.example.test/api/lti/platform/ags/{$course->getKey()}/lineitems/", $claims[Lti::CLAIM_AGS]['lineitem']);
        $this->assertArrayNotHasKey('email', $claims, 'no personal data unless the tool is allowed to receive it');

        $this->assertDatabaseHas('lti_launches', [
            'direction' => 'platform', 'lti_tool_id' => $this->tool->getKey(), 'user_id' => $student->getKey(),
            'topic_id' => $topic->getKey(), 'course_id' => $course->getKey(), 'status' => 'ok',
        ]);
    }

    public function testTheLoginHintIsSingleUse(): void
    {
        $login = $this->loginParams();

        $this->authorizeRequest($login)->assertOk();
        $this->authorizeRequest($login)->assertUnauthorized()->assertSee('already used');
    }

    public function testAuthorizeRejectsUnregisteredRedirectUrisUnknownClientsAndForeignHints(): void
    {
        $login = $this->loginParams();

        $this->authorizeRequest($login, ['redirect_uri' => 'https://evil.example.test/steal'])->assertStatus(400);
        $this->authorizeRequest($login, ['client_id' => 'nope'])->assertUnauthorized();
        $this->authorizeRequest($login, ['response_type' => 'code'])->assertStatus(400);
        $this->authorizeRequest($login, ['nonce' => ''])->assertStatus(400);

        $other = LtiTool::factory()->create(['launch_url' => 'https://other.example.test/launch']);
        $this->authorizeRequest($login, ['client_id' => $other->client_id, 'redirect_uri' => 'https://other.example.test/launch'])
            ->assertUnauthorized()
            ->assertSee('another tool');
        $this->authorizeRequest($login, ['login_hint' => $login['login_hint'] . 'x'])->assertStatus(400);
    }

    public function testLaunchRequiresAccessToTheTopicAndAnEnabledTool(): void
    {
        [$course, , $topic] = $this->courseWithLink($this->tool);

        $this->postJson("/api/lti/launches/{$topic->getKey()}")->assertUnauthorized();
        $this->actingAs($this->makeStudent(), 'api')->postJson("/api/lti/launches/{$topic->getKey()}")->assertForbidden();

        $student = $this->makeStudent();
        $this->enrol($student, $course);
        $this->tool->update(['enabled' => false]);
        $this->actingAs($student, 'api')->postJson("/api/lti/launches/{$topic->getKey()}")->assertStatus(409);
        $this->actingAs($student, 'api')->postJson('/api/lti/launches/999999999')->assertNotFound();
    }

    public function testInstructorsAndAdminsGetInstructorRolesAndPersonalDataOnlyWhenShared(): void
    {
        $this->tool->update(['share_name' => true, 'share_email' => true]);
        $admin = $this->makeAdmin(['first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.test']);

        $claims = $this->idToken($this->authorizeRequest($this->loginParams($admin)));

        $this->assertContains(Lti::ROLE_INSTRUCTOR, $claims[Lti::CLAIM_ROLES]);
        $this->assertContains(Lti::ROLE_ADMINISTRATOR, $claims[Lti::CLAIM_ROLES]);
        $this->assertSame('Ada Lovelace', $claims['name']);
        $this->assertSame('ada@example.test', $claims['email']);
    }

    public function testJwksIsPublicAndAlsoUnderWellKnown(): void
    {
        $keys = $this->getJson('/api/lti/jwks')->assertOk()->json('keys');
        $this->assertCount(2, $keys);
        $this->assertSame($keys, $this->getJson('/.well-known/jwks.json')->json('keys'));
    }

    /**
     * Launch parameters as the tool receives them at its OIDC login URL.
     */
    private function loginParams($user = null): array
    {
        [$course, , $topic] = $this->courseWithLink($this->tool);
        $user ??= $this->makeStudent();
        $this->enrol($user, $course);
        $url = $this->actingAs($user, 'api')->postJson("/api/lti/launches/{$topic->getKey()}")->json('data.url');
        $this->app['auth']->forgetGuards();
        parse_str(parse_url($url, PHP_URL_QUERY), $login);

        return $login;
    }

    /**
     * The tool's OIDC authentication request to us.
     */
    private function authorizeRequest(array $login, array $override = []): TestResponse
    {
        return $this->get('/api/lti/platform/authorize?' . http_build_query(array_merge([
            'scope' => 'openid',
            'response_type' => 'id_token',
            'response_mode' => 'form_post',
            'prompt' => 'none',
            'client_id' => $login['client_id'],
            'redirect_uri' => $login['target_link_uri'],
            'login_hint' => $login['login_hint'],
            'lti_message_hint' => $login['lti_message_hint'],
            'state' => 'tool-state',
            'nonce' => 'n-1',
        ], $override)));
    }

    private function idToken(TestResponse $response): array
    {
        $this->assertSame(1, preg_match('/name="id_token" value="([^"]+)"/', $response->getContent(), $m), $response->getContent());
        $jwks = $this->getJson('/api/lti/jwks')->json();

        return json_decode(json_encode(JWT::decode($m[1], JWK::parseKeySet($jwks, 'RS256'))), true);
    }
}
