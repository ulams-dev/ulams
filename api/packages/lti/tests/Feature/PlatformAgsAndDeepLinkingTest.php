<?php

namespace Ulams\Lti\Tests\Feature;

use Illuminate\Support\Str;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Enum\ProgressStatus;
use Ulams\Courses\Models\Topic;
use Ulams\Lti\Models\LtiLineItem;
use Ulams\Lti\Models\LtiLink;
use Ulams\Lti\Models\LtiTool;
use Ulams\Lti\Services\KeyService;
use Ulams\Lti\Support\Lti;
use Ulams\Lti\Tests\Support\KeyPair;
use Ulams\Lti\Tests\TestCase;

class PlatformAgsAndDeepLinkingTest extends TestCase
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

    public function testAScoreFromTheToolCompletesTheTopicAndHistoryIsKept(): void
    {
        [$course, , $topic, $student, $lineItemUrl] = $this->launched();
        $token = $this->accessToken([Lti::SCOPE_SCORE, Lti::SCOPE_RESULT_READONLY, Lti::SCOPE_LINEITEM]);
        \Illuminate\Support\Facades\Event::fake([\Ulams\Courses\Events\TopicFinished::class]);

        $this->ags($token, 'POST', $lineItemUrl . '/scores', [
            'userId' => (string) $student->getKey(), 'scoreGiven' => 4, 'scoreMaximum' => 10,
            'activityProgress' => 'InProgress', 'gradingProgress' => 'Pending', 'timestamp' => now()->toIso8601String(),
        ])->assertNoContent();
        $this->assertDatabaseMissing('course_progress', ['topic_id' => $topic->getKey(), 'user_id' => $student->getKey(), 'status' => ProgressStatus::COMPLETE]);

        $this->ags($token, 'POST', $lineItemUrl . '/scores', [
            'userId' => (string) $student->getKey(), 'scoreGiven' => 9, 'scoreMaximum' => 10,
            'activityProgress' => 'Completed', 'gradingProgress' => 'FullyGraded', 'timestamp' => now()->toIso8601String(),
        ])->assertNoContent();

        $this->assertDatabaseHas('course_progress', ['topic_id' => $topic->getKey(), 'user_id' => $student->getKey(), 'status' => ProgressStatus::COMPLETE]);
        \Illuminate\Support\Facades\Event::assertDispatched(\Ulams\Courses\Events\TopicFinished::class);
        $this->assertDatabaseCount('lti_scores', 2);
        $this->ags($token, 'GET', $lineItemUrl . '/results')
            ->assertOk()
            ->assertJsonPath('0.resultScore', 9)
            ->assertJsonPath('0.userId', (string) $student->getKey());
        $this->ags($token, 'GET', "https://lms.example.test/api/lti/platform/ags/{$course->getKey()}/lineitems")
            ->assertOk()
            ->assertJsonPath('0.id', $lineItemUrl)
            ->assertJsonPath('0.resourceLinkId', 'topic-' . $topic->getKey());
    }

    public function testScoresOnlyForLearnersTheToolWasLaunchedFor(): void
    {
        [, , , , $lineItemUrl] = $this->launched();
        $token = $this->accessToken([Lti::SCOPE_SCORE]);

        $this->ags($token, 'POST', $lineItemUrl . '/scores', [
            'userId' => (string) $this->makeStudent()->getKey(),
            'activityProgress' => 'Completed', 'gradingProgress' => 'FullyGraded',
        ])->assertStatus(400);
    }

    public function testLineItemsCanBeCreatedAndScopesAreEnforced(): void
    {
        [$course, , $topic] = $this->launched();
        $readOnly = $this->accessToken([Lti::SCOPE_LINEITEM_READONLY]);
        $base = "https://lms.example.test/api/lti/platform/ags/{$course->getKey()}/lineitems";

        $this->ags($readOnly, 'POST', $base, ['label' => 'Quiz', 'scoreMaximum' => 5])->assertForbidden();
        $this->ags($readOnly, 'POST', $base . '/1/scores', [])->assertForbidden();

        $full = $this->accessToken([Lti::SCOPE_LINEITEM]);
        $created = $this->ags($full, 'POST', $base, ['label' => 'Quiz', 'scoreMaximum' => 5, 'resourceLinkId' => 'topic-' . $topic->getKey(), 'tag' => 'quiz'])
            ->assertCreated()
            ->json();
        $this->assertSame('Quiz', $created['label']);
        $this->ags($full, 'PUT', $created['id'], ['label' => 'Final quiz', 'scoreMaximum' => 10, 'tag' => 'quiz'])->assertOk()->assertJsonPath('scoreMaximum', 10);
        $this->ags($full, 'GET', $base . '?tag=quiz')->assertOk()->assertJsonCount(1);
        $this->ags($full, 'DELETE', $created['id'])->assertNoContent();
        $this->ags($full, 'POST', $base, ['label' => '', 'scoreMaximum' => 5])->assertStatus(400);
    }

    public function testTokenRequestsNeedAFreshCorrectlyAddressedSignedAssertion(): void
    {
        $assertion = $this->assertion();

        $this->tokenRequest($assertion, Lti::SCOPE_SCORE)->assertOk()->assertJsonPath('token_type', 'Bearer');
        $this->tokenRequest($assertion, Lti::SCOPE_SCORE)->assertUnauthorized()->assertJsonPath('error', 'invalid_client');
        $this->tokenRequest($this->assertion(['aud' => 'https://elsewhere.test/token']), Lti::SCOPE_SCORE)->assertUnauthorized();
        $this->tokenRequest((new KeyPair())->sign($this->assertionClaims()), Lti::SCOPE_SCORE)->assertUnauthorized();
        $this->tokenRequest($this->assertion(), 'https://example.test/unknown-scope')->assertStatus(400)->assertJsonPath('error', 'invalid_scope');
        $this->postJson('/api/lti/platform/token', ['grant_type' => 'password'])->assertStatus(400);
    }

    public function testAgsRejectsMissingForgedAndExpiredTokens(): void
    {
        [, , , , $lineItemUrl] = $this->launched();

        $this->getJson($lineItemUrl)->assertUnauthorized();
        $this->ags('not-a-token', 'GET', $lineItemUrl)->assertUnauthorized();
        // a token signed with keys that are not ours
        $this->ags((new KeyPair())->sign(['typ' => 'lti-ags', 'iss' => Lti::issuer(), 'tool' => $this->tool->getKey(), 'sub' => $this->tool->client_id, 'scope' => Lti::SCOPE_LINEITEM, 'exp' => time() + 60]), 'GET', $lineItemUrl)
            ->assertUnauthorized();
        // expired
        $expired = app(KeyService::class)->sign(['typ' => 'lti-ags', 'iss' => Lti::issuer(), 'tool' => $this->tool->getKey(), 'sub' => $this->tool->client_id, 'scope' => Lti::SCOPE_LINEITEM, 'exp' => time() - 120]);
        $this->ags($expired, 'GET', $lineItemUrl)->assertUnauthorized();
    }

    public function testAToolCannotReachLineItemsOrCoursesOfAnotherTool(): void
    {
        [$course, , , , $lineItemUrl] = $this->launched();
        $otherKeys = new KeyPair();
        $other = LtiTool::factory()->create(['public_key' => $otherKeys->public, 'jwks_url' => null]);
        $otherToken = $this->tokenRequest($otherKeys->sign($this->assertionClaims($other)), Lti::SCOPE_LINEITEM)->json('access_token');

        // the course has no link to the other tool
        $this->ags($otherToken, 'GET', "https://lms.example.test/api/lti/platform/ags/{$course->getKey()}/lineitems")->assertNotFound();

        // even in a course that links both tools, the first tool's line item is invisible
        $this->courseWithLinkInto($course, $other);
        $this->ags($otherToken, 'GET', $lineItemUrl)->assertNotFound();
        $this->ags($otherToken, 'GET', "https://lms.example.test/api/lti/platform/ags/{$course->getKey()}/lineitems")->assertOk()->assertJsonCount(0);
    }

    public function testDeepLinkingCreatesTopicsFromTheToolsSelection(): void
    {
        [$course, $lesson] = $this->courseWithLink($this->tool);
        $admin = $this->makeAdmin();

        $url = $this->actingAs($admin, 'api')
            ->postJson("/api/admin/lti/tools/{$this->tool->getKey()}/deep-link", ['lesson_id' => $lesson->getKey()])
            ->assertOk()
            ->json('data.url');
        $this->app['auth']->forgetGuards();
        parse_str(parse_url($url, PHP_URL_QUERY), $login);
        $this->assertSame('https://tool.example.test/lti/deep-link', $login['target_link_uri']);

        $authorize = $this->get('/api/lti/platform/authorize?' . http_build_query([
            'scope' => 'openid', 'response_type' => 'id_token', 'response_mode' => 'form_post',
            'client_id' => $this->tool->client_id, 'redirect_uri' => $login['target_link_uri'],
            'login_hint' => $login['login_hint'], 'nonce' => 'n-2', 'state' => 's-2',
        ]))->assertOk();
        preg_match('/name="id_token" value="([^"]+)"/', $authorize->getContent(), $m);
        $request = json_decode(base64_decode(strtr(explode('.', $m[1])[1], '-_', '+/')), true);
        $this->assertSame(Lti::MSG_DEEP_LINKING_REQUEST, $request[Lti::CLAIM_MESSAGE_TYPE]);
        $settings = $request[Lti::CLAIM_DL_SETTINGS];
        $this->assertSame('https://lms.example.test/api/lti/platform/deep-links', $settings['deep_link_return_url']);

        $response = $this->deepLinkResponse($settings['data'], [
            ['type' => 'ltiResourceLink', 'title' => 'Graphing lines', 'url' => 'https://tool.example.test/lti/launch?item=1', 'custom' => ['item' => '1'], 'lineItem' => ['scoreMaximum' => 20]],
            ['type' => 'ltiResourceLink', 'title' => 'Slopes', 'url' => 'https://tool.example.test/lti/launch?item=2', 'window' => ['targetName' => '_blank']],
            ['type' => 'html', 'html' => '<script>alert(1)</script>'],
        ]);

        $this->post('/api/lti/platform/deep-links', ['JWT' => $response])->assertOk()->assertSee('2 items added');
        $topics = Topic::query()->where('lesson_id', $lesson->getKey())->where('topicable_type', LtiLink::class)->with('topicable')->get()->keyBy('title');
        $this->assertSame('https://tool.example.test/lti/launch?item=1', $topics['Graphing lines']->topicable->url);
        $this->assertSame(['item' => '1'], $topics['Graphing lines']->topicable->custom);
        $this->assertEquals(20, $topics['Graphing lines']->topicable->score_maximum);
        $this->assertSame('window', $topics['Slopes']->topicable->presentation);

        // replay
        $this->post('/api/lti/platform/deep-links', ['JWT' => $response])->assertUnauthorized();
    }

    public function testDeepLinkingResponsesMustBeSignedByTheToolAndCarryOurData(): void
    {
        [, $lesson] = $this->courseWithLink($this->tool);
        $data = app(\Ulams\Lti\Support\HintSigner::class)->sign('deep_link_data', ['uid' => $this->makeAdmin()->getKey(), 'tool' => $this->tool->getKey(), 'lesson' => $lesson->getKey()], 600);
        $items = [['type' => 'ltiResourceLink', 'title' => 'X']];

        $this->post('/api/lti/platform/deep-links', ['JWT' => (new KeyPair())->sign($this->deepLinkClaims($data, $items))])->assertUnauthorized();
        $this->post('/api/lti/platform/deep-links', ['JWT' => $this->deepLinkResponse('forged-data', $items)])->assertStatus(400);
        $this->post('/api/lti/platform/deep-links', ['JWT' => $this->toolKeys->sign(['aud' => 'https://elsewhere.test'] + $this->deepLinkClaims($data, $items))])->assertUnauthorized();
        $this->post('/api/lti/platform/deep-links', [])->assertStatus(400);
        $this->assertSame(0, Topic::query()->where('lesson_id', $lesson->getKey())->where('title', 'X')->count());
    }

    public function testDeepLinkInitiationNeedsLtiManageAndAccessToTheCourse(): void
    {
        [, $lesson] = $this->courseWithLink($this->tool);

        $this->actingAs($this->makeStudent(), 'api')
            ->postJson("/api/admin/lti/tools/{$this->tool->getKey()}/deep-link", ['lesson_id' => $lesson->getKey()])
            ->assertForbidden();
    }

    /**
     * A course with a link to the tool, a learner launched into it.
     */
    private function launched(): array
    {
        [$course, $lesson, $topic] = $this->courseWithLink($this->tool);
        $student = $this->makeStudent();
        $this->enrol($student, $course);
        $url = $this->actingAs($student, 'api')->postJson("/api/lti/launches/{$topic->getKey()}")->json('data.url');
        $this->app['auth']->forgetGuards();
        parse_str(parse_url($url, PHP_URL_QUERY), $login);
        $response = $this->get('/api/lti/platform/authorize?' . http_build_query([
            'scope' => 'openid', 'response_type' => 'id_token', 'client_id' => $this->tool->client_id,
            'redirect_uri' => $login['target_link_uri'], 'login_hint' => $login['login_hint'], 'nonce' => Str::random(),
        ]))->assertOk();
        preg_match('/name="id_token" value="([^"]+)"/', $response->getContent(), $m);
        $claims = json_decode(base64_decode(strtr(explode('.', $m[1])[1], '-_', '+/')), true);

        return [$course, $lesson, $topic, $student, $claims[Lti::CLAIM_AGS]['lineitem']];
    }

    private function courseWithLinkInto($course, LtiTool $tool): void
    {
        $lesson = \Ulams\Courses\Models\Lesson::factory()->create(['course_id' => $course->getKey()]);
        $content = LtiLink::query()->create(['lti_tool_id' => $tool->getKey()]);
        $topic = Topic::factory()->create(['lesson_id' => $lesson->getKey()]);
        $topic->topicable()->associate($content)->save();
    }

    private function assertionClaims(?LtiTool $tool = null): array
    {
        $tool ??= $this->tool;

        return [
            'iss' => $tool->client_id,
            'sub' => $tool->client_id,
            'aud' => ['https://lms.example.test/api/lti/platform/token'],
            'iat' => time(),
            'exp' => time() + 60,
            'jti' => Str::uuid()->toString(),
        ];
    }

    private function assertion(array $override = []): string
    {
        return $this->toolKeys->sign(array_merge($this->assertionClaims(), $override));
    }

    private function tokenRequest(string $assertion, string $scope)
    {
        return $this->post('/api/lti/platform/token', [
            'grant_type' => 'client_credentials',
            'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
            'client_assertion' => $assertion,
            'scope' => $scope,
        ]);
    }

    private function accessToken(array $scopes): string
    {
        return $this->tokenRequest($this->assertion(), implode(' ', $scopes))->assertOk()->json('access_token');
    }

    private function ags(string $token, string $method, string $url, array $body = [])
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . $token])->json($method, $url, $body);
    }

    private function deepLinkClaims(string $data, array $items): array
    {
        return [
            'iss' => $this->tool->client_id,
            'aud' => [Lti::issuer()],
            'iat' => time(),
            'exp' => time() + 600,
            'nonce' => Str::uuid()->toString(),
            Lti::CLAIM_DEPLOYMENT_ID => $this->tool->deployment_id,
            Lti::CLAIM_MESSAGE_TYPE => Lti::MSG_DEEP_LINKING_RESPONSE,
            Lti::CLAIM_VERSION => '1.3.0',
            Lti::CLAIM_DL_CONTENT_ITEMS => $items,
            Lti::CLAIM_DL_DATA => $data,
        ];
    }

    private function deepLinkResponse(string $data, array $items): string
    {
        return $this->toolKeys->sign($this->deepLinkClaims($data, $items));
    }
}
