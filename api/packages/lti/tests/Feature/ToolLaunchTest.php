<?php

namespace Ulams\Lti\Tests\Feature;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Psr\Http\Message\RequestInterface;
use Spatie\Permission\Models\Role;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Enum\ProgressStatus;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\Courses\Repositories\Contracts\CourseProgressRepositoryContract;
use Ulams\Lti\Jobs\SendGradeToPlatform;
use Ulams\Lti\Models\LtiGradeTarget;
use Ulams\Lti\Models\LtiPlatform;
use Ulams\Lti\Models\LtiUserLink;
use Ulams\Lti\Support\Lti;
use Ulams\Lti\Tests\Support\KeyPair;
use Ulams\Lti\Tests\TestCase;
use Ulams\Lti\Tool\ToolLaunchService;

class ToolLaunchTest extends TestCase
{
    use CreatesUsers;

    private KeyPair $platformKeys;
    private LtiPlatform $platform;
    private Course $course;
    /** @var RequestInterface[] */
    private array $outgoing = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['student', 'tutor'] as $role) {
            Role::findOrCreate($role, 'api');
        }
        $this->platformKeys = new KeyPair();
        $this->platform = LtiPlatform::factory()->create([
            'issuer' => 'https://moodle.example.test',
            'client_id' => 'moodle-client',
            'deployment_ids' => ['dep-1'],
            'jwks_url' => 'https://moodle.example.test/mod/lti/certs.php',
            'auth_token_url' => 'https://moodle.example.test/mod/lti/token.php',
        ]);
        $this->course = Course::factory()->create(['status' => 'published', 'title' => 'Intro to Git']);
        app(ToolLaunchService::class)->setHttpClient($this->fakePlatformHttp());
    }

    public function testALearnerLaunchedFromMoodleGetsAnAccountCourseAccessAndASignInCode(): void
    {
        [$state, $nonce] = $this->oidcLogin();

        $response = $this->post('/api/lti/tool/launch', [
            'id_token' => $this->idToken($nonce, ['email' => 'learner@school.test', 'given_name' => 'Lea', 'family_name' => 'Rner']),
            'state' => $state,
        ]);

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('https://app.example.test/lti/launch?code=', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame((string) $this->course->getKey(), $query['course']);

        $link = LtiUserLink::query()->where('lti_platform_id', $this->platform->getKey())->where('sub', 'moodle-7')->firstOrFail();
        $user = config('auth.providers.users.model')::query()->findOrFail($link->user_id);
        $this->assertSame('learner@school.test', $user->email);
        $this->assertTrue($user->hasRole('student'));
        $this->assertTrue($this->course->users()->whereKey($user->getKey())->exists(), 'course access granted');
        $this->assertDatabaseHas('lti_grade_targets', [
            'lti_platform_id' => $this->platform->getKey(), 'user_id' => $user->getKey(), 'course_id' => $this->course->getKey(),
            'lineitem' => 'https://moodle.example.test/mod/lti/services.php/2/lineitems/9/lineitem',
        ]);

        $exchange = $this->postJson('/api/lti/tool/exchange', ['code' => $query['code']])->assertOk();
        $this->assertSame($this->course->getKey(), $exchange->json('data.course_id'));
        $this->withToken($exchange->json('data.token'))->getJson('/api/profile/me')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/lti/tool/exchange', ['code' => $query['code']])->assertUnauthorized();
    }

    public function testTheSameLearnerIsRecognisedOnTheNextLaunch(): void
    {
        foreach ([1, 2] as $_) {
            [$state, $nonce] = $this->oidcLogin();
            $this->post('/api/lti/tool/launch', ['id_token' => $this->idToken($nonce), 'state' => $state])->assertRedirect();
        }

        $this->assertSame(1, LtiUserLink::query()->where('sub', 'moodle-7')->count());
    }

    public function testAPlatformCannotTakeOverALocalAccountByEmail(): void
    {
        $admin = $this->makeAdmin(['email' => 'admin@ulams.test']);
        [$state, $nonce] = $this->oidcLogin();

        $this->post('/api/lti/tool/launch', ['id_token' => $this->idToken($nonce, ['email' => 'admin@ulams.test']), 'state' => $state])->assertRedirect();

        $link = LtiUserLink::query()->where('sub', 'moodle-7')->firstOrFail();
        $this->assertNotSame($admin->getKey(), $link->user_id);
        $user = config('auth.providers.users.model')::query()->findOrFail($link->user_id);
        $this->assertStringEndsWith('@lti.invalid', $user->email);
        $this->assertFalse($user->hasRole('admin'));
    }

    public function testInstructorsBecomeTutorsButNeverAdmins(): void
    {
        [$state, $nonce] = $this->oidcLogin();

        $this->post('/api/lti/tool/launch', ['id_token' => $this->idToken($nonce, [
            Lti::CLAIM_ROLES => [Lti::ROLE_INSTRUCTOR, Lti::ROLE_SYSTEM_ADMINISTRATOR],
        ]), 'state' => $state])->assertRedirect();

        $user = config('auth.providers.users.model')::query()->findOrFail(LtiUserLink::query()->where('sub', 'moodle-7')->value('user_id'));
        $this->assertTrue($user->hasRole('tutor'));
        $this->assertFalse($user->hasRole('admin'));
    }

    public function testLaunchesAreRejectedOnReplayBadSignatureUnknownDeploymentOrWrongAudience(): void
    {
        [$state, $nonce] = $this->oidcLogin();
        $token = $this->idToken($nonce);
        $this->post('/api/lti/tool/launch', ['id_token' => $token, 'state' => $state])->assertRedirect();
        $this->post('/api/lti/tool/launch', ['id_token' => $token, 'state' => $state])->assertUnauthorized();

        [$state, $nonce] = $this->oidcLogin();
        $forged = (new KeyPair($this->platformKeys->kid))->sign($this->claims($nonce));
        $this->post('/api/lti/tool/launch', ['id_token' => $forged, 'state' => $state])->assertUnauthorized();

        [$state, $nonce] = $this->oidcLogin();
        $this->post('/api/lti/tool/launch', ['id_token' => $this->idToken($nonce, [Lti::CLAIM_DEPLOYMENT_ID => 'dep-unknown']), 'state' => $state])
            ->assertUnauthorized();

        [$state, $nonce] = $this->oidcLogin();
        $this->post('/api/lti/tool/launch', ['id_token' => $this->idToken($nonce, ['aud' => 'someone-else']), 'state' => $state])
            ->assertUnauthorized();

        [$state] = $this->oidcLogin();
        $this->post('/api/lti/tool/launch', ['id_token' => $this->idToken('nonce-never-issued'), 'state' => $state])->assertUnauthorized();

        $this->assertSame(0, LtiUserLink::query()->where('sub', 'moodle-7')->count() - 1, 'only the first launch created a user');
    }

    public function testLoginRequiresAKnownEnabledPlatform(): void
    {
        $this->get('/api/lti/tool/login?' . http_build_query(['iss' => 'https://unknown.test', 'login_hint' => 'x', 'client_id' => 'y']))
            ->assertStatus(400);
        $this->platform->update(['enabled' => false]);
        $this->get('/api/lti/tool/login?' . http_build_query(['iss' => 'https://moodle.example.test', 'login_hint' => 'x', 'client_id' => 'moodle-client']))
            ->assertStatus(400);
    }

    public function testDeepLinkingLetsAnInstructorPickCoursesAndSignsTheResponse(): void
    {
        [$state, $nonce] = $this->oidcLogin();
        $picker = $this->post('/api/lti/tool/launch', ['id_token' => $this->idToken($nonce, [
            Lti::CLAIM_MESSAGE_TYPE => Lti::MSG_DEEP_LINKING_REQUEST,
            Lti::CLAIM_ROLES => [Lti::ROLE_INSTRUCTOR],
            Lti::CLAIM_DL_SETTINGS => [
                'deep_link_return_url' => 'https://moodle.example.test/mod/lti/contentitem_return.php',
                'accept_types' => ['ltiResourceLink'],
                'accept_presentation_document_targets' => ['iframe', 'window'],
                'data' => 'moodle-opaque-data',
            ],
        ]), 'state' => $state])->assertOk();
        $picker->assertSee('Intro to Git');
        preg_match('/name="form_token" value="([^"]+)"/', $picker->getContent(), $m);

        $response = $this->post('/api/lti/tool/deep-link', ['form_token' => $m[1], 'course_ids' => [$this->course->getKey()]])->assertOk();
        $this->assertStringContainsString('action="https://moodle.example.test/mod/lti/contentitem_return.php"', $response->getContent());
        preg_match('/name="JWT" value="([^"]+)"/', $response->getContent(), $jwt);

        $claims = json_decode(json_encode(JWT::decode($jwt[1], JWK::parseKeySet($this->getJson('/api/lti/jwks')->json(), 'RS256'))), true);
        $this->assertSame(Lti::MSG_DEEP_LINKING_RESPONSE, $claims[Lti::CLAIM_MESSAGE_TYPE]);
        $this->assertSame('moodle-client', $claims['iss']);
        $this->assertSame(['https://moodle.example.test'], $claims['aud']);
        $this->assertSame('moodle-opaque-data', $claims[Lti::CLAIM_DL_DATA]);
        $this->assertSame((string) $this->course->getKey(), $claims[Lti::CLAIM_DL_CONTENT_ITEMS][0]['custom']['course_id']);
        $this->assertSame('https://lms.example.test/api/lti/tool/launch', $claims[Lti::CLAIM_DL_CONTENT_ITEMS][0]['url']);

        // the picker form works once
        $this->post('/api/lti/tool/deep-link', ['form_token' => $m[1], 'course_ids' => [$this->course->getKey()]])->assertStatus(410);
    }

    public function testLearnersCannotOpenTheDeepLinkingPicker(): void
    {
        [$state, $nonce] = $this->oidcLogin();
        $this->post('/api/lti/tool/launch', ['id_token' => $this->idToken($nonce, [
            Lti::CLAIM_MESSAGE_TYPE => Lti::MSG_DEEP_LINKING_REQUEST,
            Lti::CLAIM_DL_SETTINGS => ['deep_link_return_url' => 'https://moodle.example.test/r', 'accept_types' => ['ltiResourceLink'], 'accept_presentation_document_targets' => ['iframe']],
        ]), 'state' => $state])->assertForbidden();
    }

    public function testFinishingATopicQueuesAGradeAndTheJobSendsItToThePlatform(): void
    {
        $lesson = Lesson::factory()->create(['course_id' => $this->course->getKey()]);
        $topics = Topic::factory()->count(2)->create(['lesson_id' => $lesson->getKey(), 'active' => true]);
        [$state, $nonce] = $this->oidcLogin();
        $this->post('/api/lti/tool/launch', ['id_token' => $this->idToken($nonce), 'state' => $state])->assertRedirect();
        $user = config('auth.providers.users.model')::query()->findOrFail(LtiUserLink::query()->where('sub', 'moodle-7')->value('user_id'));

        Queue::fake();
        app(CourseProgressRepositoryContract::class)->updateInTopic($topics[0], $user, ProgressStatus::IN_PROGRESS);
        app(CourseProgressRepositoryContract::class)->updateInTopic($topics[0], $user, ProgressStatus::COMPLETE);
        Queue::assertPushed(SendGradeToPlatform::class, 1);

        $target = LtiGradeTarget::query()->where('user_id', $user->getKey())->firstOrFail();
        $this->outgoing = [];
        (new SendGradeToPlatform($target->getKey()))->handle(app(\Ulams\Lti\Tool\ToolDatabase::class), app(ToolLaunchService::class));

        $score = collect($this->outgoing)->first(fn (RequestInterface $r) => str_ends_with($r->getUri()->getPath(), '/lineitem/scores'));
        $this->assertNotNull($score, 'score posted to the platform line item');
        $this->assertSame('Bearer platform-access-token', $score->getHeaderLine('Authorization'));
        $body = json_decode((string) $score->getBody(), true);
        $this->assertSame('moodle-7', $body['userId']);
        $this->assertEquals(50, $body['scoreGiven']);
        $this->assertSame('InProgress', $body['activityProgress']);
        $this->assertNotNull($target->refresh()->last_sent_at);

        // the token request was a JWT assertion signed with our key, for the platform's token URL
        $tokenRequest = collect($this->outgoing)->first(fn (RequestInterface $r) => str_ends_with((string) $r->getUri(), '/token.php'));
        parse_str((string) $tokenRequest->getBody(), $form);
        $assertion = JWT::decode($form['client_assertion'], JWK::parseKeySet($this->getJson('/api/lti/jwks')->json(), 'RS256'));
        $this->assertSame('moodle-client', $assertion->iss);
    }

    /**
     * @return array{0: string, 1: string} state and nonce from the OIDC redirect
     */
    private function oidcLogin(): array
    {
        $response = $this->get('/api/lti/tool/login?' . http_build_query([
            'iss' => 'https://moodle.example.test',
            'login_hint' => 'moodle-7',
            'target_link_uri' => 'https://lms.example.test/api/lti/tool/launch',
            'client_id' => 'moodle-client',
            'lti_message_hint' => 'abc',
        ]));
        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('https://moodle.example.test/mod/lti/auth.php?', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $params);
        $this->assertSame('https://lms.example.test/api/lti/tool/launch', $params['redirect_uri']);

        return [$params['state'], $params['nonce']];
    }

    private function claims(string $nonce, array $override = []): array
    {
        return array_merge([
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
            Lti::CLAIM_AGS => [
                'scope' => [Lti::SCOPE_LINEITEM, Lti::SCOPE_SCORE],
                'lineitems' => 'https://moodle.example.test/mod/lti/services.php/2/lineitems',
                'lineitem' => 'https://moodle.example.test/mod/lti/services.php/2/lineitems/9/lineitem',
            ],
        ], $override);
    }

    private function idToken(string $nonce, array $override = []): string
    {
        return $this->platformKeys->sign($this->claims($nonce, $override));
    }

    /**
     * The platform as seen by our HTTP client: JWKS, token endpoint, AGS.
     */
    private function fakePlatformHttp(): Client
    {
        return new Client(['handler' => function (RequestInterface $request) {
            $this->outgoing[] = $request;
            $uri = (string) $request->getUri();

            return Create::promiseFor(match (true) {
                str_ends_with($uri, '/certs.php') => new Response(200, ['Content-Type' => 'application/json'], json_encode($this->platformKeys->jwks())),
                str_ends_with($uri, '/token.php') => new Response(200, ['Content-Type' => 'application/json'], json_encode(['access_token' => 'platform-access-token', 'expires_in' => 3600])),
                default => new Response(200, ['Content-Type' => 'application/json'], '{}'),
            });
        }]);
    }
}
