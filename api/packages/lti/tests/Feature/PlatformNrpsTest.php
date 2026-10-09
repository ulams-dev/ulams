<?php

namespace Ulams\Lti\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Models\Course;
use Ulams\Lti\Models\LtiTool;
use Ulams\Lti\Services\KeyService;
use Ulams\Lti\Support\Lti;
use Ulams\Lti\Tests\Support\KeyPair;
use Ulams\Lti\Tests\TestCase;

/**
 * Names and Role Provisioning Services 2.0 on the platform side (IMS NRPS 2.0): the launch claim,
 * the scoped token, the membership container, paging with Link headers, role filter, PII.
 */
class PlatformNrpsTest extends TestCase
{
    use CreatesUsers;

    private KeyPair $toolKeys;
    private LtiTool $tool;
    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->toolKeys = new KeyPair();
        $this->tool = LtiTool::factory()->create(['public_key' => $this->toolKeys->public, 'jwks_url' => null, 'nrps_enabled' => true]);
        [$this->course] = $this->courseWithLink($this->tool);
    }

    public function testAMembershipContainerListsLearnersAndInstructorsWithoutPersonalDataByDefault(): void
    {
        $student = $this->makeStudent(['first_name' => 'Lea', 'last_name' => 'Rner', 'email' => 'lea@school.test']);
        $tutor = $this->makeInstructor();
        $outsider = $this->makeStudent();
        $this->enrol($student, $this->course);
        $this->course->authors()->attach($tutor->getKey());

        $response = $this->nrps($this->token())->assertOk();

        $this->assertStringContainsString('application/vnd.ims.lti-nrps.v2.membershipcontainer+json', $response->headers->get('Content-Type'));
        $response->assertJsonPath('id', 'https://lms.example.test/api/lti/platform/nrps/' . $this->course->getKey())
            ->assertJsonPath('context.id', 'course-' . $this->course->getKey())
            ->assertJsonCount(2, 'members');
        $members = collect($response->json('members'))->keyBy('user_id');
        $this->assertSame([Lti::ROLE_LEARNER], $members[(string) $student->getKey()]['roles']);
        $this->assertSame([Lti::ROLE_INSTRUCTOR], $members[(string) $tutor->getKey()]['roles']);
        $this->assertSame('Active', $members[(string) $student->getKey()]['status']);
        $this->assertArrayNotHasKey((string) $outsider->getKey(), $members->all());
        // no PII unless the registration shares it
        $this->assertArrayNotHasKey('name', $members[(string) $student->getKey()]);
        $this->assertArrayNotHasKey('email', $members[(string) $student->getKey()]);
        $this->assertStringNotContainsString('lea@school.test', $response->getContent());
        $this->assertStringNotContainsString('Rner', $response->getContent());
    }

    public function testNamesAndEmailsAreIncludedOnlyWhenTheRegistrationSharesThem(): void
    {
        $student = $this->makeStudent(['first_name' => 'Lea', 'last_name' => 'Rner', 'email' => 'lea@school.test']);
        $this->enrol($student, $this->course);

        $this->tool->update(['share_name' => true]);
        $member = $this->nrps($this->token())->assertOk()->json('members.0');
        $this->assertSame('Lea Rner', $member['name']);
        $this->assertSame('Lea', $member['given_name']);
        $this->assertArrayNotHasKey('email', $member);

        $this->tool->update(['share_name' => false, 'share_email' => true]);
        $member = $this->nrps($this->token())->assertOk()->json('members.0');
        $this->assertSame('lea@school.test', $member['email']);
        $this->assertArrayNotHasKey('name', $member);
    }

    public function testMembersArePagedWithLinkHeaders(): void
    {
        foreach (range(1, 5) as $_) {
            $this->enrol($this->makeStudent(), $this->course);
        }
        $token = $this->token();
        $base = 'https://lms.example.test/api/lti/platform/nrps/' . $this->course->getKey();

        $first = $this->nrps($token, '?limit=2')->assertOk()->assertJsonCount(2, 'members');
        $this->assertSame("<{$base}?limit=2&page=2>; rel=\"next\"", $first->headers->get('Link'));

        $second = $this->nrps($token, '?limit=2&page=2')->assertOk()->assertJsonCount(2, 'members');
        $this->assertSame("<{$base}?limit=2&page=3>; rel=\"next\"", $second->headers->get('Link'));

        $last = $this->nrps($token, '?limit=2&page=3')->assertOk()->assertJsonCount(1, 'members');
        $this->assertNull($last->headers->get('Link'));

        $ids = array_merge(...array_map(fn ($r) => array_column($r->json('members'), 'user_id'), [$first, $second, $last]));
        $this->assertCount(5, array_unique($ids));

        // the page size is capped at 100
        $this->nrps($token, '?limit=1000')->assertOk()->assertJsonCount(5, 'members');
    }

    public function testTheRoleFilterKeepsItsParameterInTheLink(): void
    {
        $this->enrol($this->makeStudent(), $this->course);
        $this->enrol($this->makeStudent(), $this->course);
        $this->course->authors()->attach($this->makeInstructor()->getKey());
        $token = $this->token();

        $this->nrps($token, '?role=Instructor')->assertOk()->assertJsonCount(1, 'members')->assertJsonPath('members.0.roles.0', Lti::ROLE_INSTRUCTOR);
        $this->nrps($token, '?role=' . urlencode(Lti::ROLE_LEARNER))->assertOk()->assertJsonCount(2, 'members');
        $this->nrps($token, '?role=Mentor')->assertOk()->assertJsonCount(0, 'members');
        $paged = $this->nrps($token, '?role=Learner&limit=1')->assertOk();
        $this->assertStringContainsString('role=Learner&limit=1&page=2', (string) $paged->headers->get('Link'));
    }

    public function testTheScopeIsRequiredAndOnlyIssuedToToolsWithNrpsEnabled(): void
    {
        // an AGS-only token
        $agsOnly = $this->tokenRequest(Lti::SCOPE_SCORE)->assertOk()->json('access_token');
        $this->nrps($agsOnly)->assertForbidden()->assertJsonPath('error', 'insufficient_scope');

        // NRPS scope is dropped from the grant when the registration does not allow it
        $this->tool->update(['nrps_enabled' => false]);
        $this->assertSame(Lti::SCOPE_SCORE, $this->tokenRequest(Lti::SCOPE_NRPS . ' ' . Lti::SCOPE_SCORE)->assertOk()->json('scope'));
        $this->tokenRequest(Lti::SCOPE_NRPS)->assertStatus(400)->assertJsonPath('error', 'invalid_scope');

        // switching it off later stops tokens already issued
        $this->tool->update(['nrps_enabled' => true]);
        $token = $this->token();
        $this->nrps($token)->assertOk();
        $this->tool->update(['nrps_enabled' => false]);
        $this->nrps($token)->assertForbidden();
    }

    public function testMissingForgedAndForeignTokensAreRejected(): void
    {
        $url = '/api/lti/platform/nrps/' . $this->course->getKey();
        $this->getJson($url)->assertUnauthorized();
        $this->withHeaders(['Authorization' => 'Bearer nope'])->getJson($url)->assertUnauthorized();
        $forged = (new KeyPair())->sign(['typ' => 'lti-ags', 'iss' => Lti::issuer(), 'tool' => $this->tool->getKey(), 'sub' => $this->tool->client_id, 'scope' => Lti::SCOPE_NRPS, 'exp' => time() + 60]);
        $this->nrps($forged)->assertUnauthorized();
        $expired = app(KeyService::class)->sign(['typ' => 'lti-ags', 'iss' => Lti::issuer(), 'tool' => $this->tool->getKey(), 'sub' => $this->tool->client_id, 'scope' => Lti::SCOPE_NRPS, 'exp' => time() - 120]);
        $this->nrps($expired)->assertUnauthorized();
    }

    public function testACourseWithoutALinkOfTheToolOrAnUnknownCourseIs404(): void
    {
        $token = $this->token();
        $other = Course::factory()->create(['status' => 'published']);
        $this->enrol($this->makeStudent(), $other);

        $this->nrps($token, '', $other->getKey())->assertNotFound();
        $this->nrps($token, '', 999999)->assertNotFound();

        // another tool's course is invisible, even when it links the first tool's own course
        $otherKeys = new KeyPair();
        $otherTool = LtiTool::factory()->create(['public_key' => $otherKeys->public, 'jwks_url' => null, 'nrps_enabled' => true]);
        [$foreign] = $this->courseWithLink($otherTool);
        $this->nrps($token, '', $foreign->getKey())->assertNotFound();
    }

    public function testTokensOfAnotherTenantAreRejected(): void
    {
        $token = $this->token();
        config(['app.key' => 'base64:' . base64_encode(random_bytes(32))]);
        $this->app->forgetInstance('encrypter');
        DB::table('lti_keys')->delete();

        $this->nrps($token)->assertUnauthorized();
    }

    public function testTheLaunchAnnouncesTheServiceOnlyForToolsWithNrpsEnabled(): void
    {
        $claims = $this->launchClaims();
        $this->assertSame([
            'context_memberships_url' => 'https://lms.example.test/api/lti/platform/nrps/' . $this->course->getKey(),
            'service_versions' => ['2.0'],
        ], $claims[Lti::CLAIM_NRPS]);

        $this->tool->update(['nrps_enabled' => false]);
        $this->assertArrayNotHasKey(Lti::CLAIM_NRPS, $this->launchClaims());
    }

    public function testAdminsSwitchNrpsPerTool(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'api')
            ->putJson('/api/admin/lti/tools/' . $this->tool->getKey(), ['nrps_enabled' => false])
            ->assertOk()->assertJsonPath('data.nrps_enabled', false);
        $this->assertFalse($this->tool->refresh()->nrps_enabled);
        $this->actingAs($admin, 'api')
            ->postJson('/api/admin/lti/tools', [
                'name' => 'New', 'oidc_login_url' => 'https://t.example.test/login', 'launch_url' => 'https://t.example.test/launch',
                'jwks_url' => 'https://t.example.test/jwks',
            ])->assertCreated()->assertJsonPath('data.nrps_enabled', false);
    }

    private function launchClaims(): array
    {
        $topic = $this->course->lessons()->first()->topics()->first();
        $student = $this->makeStudent();
        $this->enrol($student, $this->course);
        $url = $this->actingAs($student, 'api')->postJson("/api/lti/launches/{$topic->getKey()}")->json('data.url');
        $this->app['auth']->forgetGuards();
        parse_str(parse_url($url, PHP_URL_QUERY), $login);
        $response = $this->get('/api/lti/platform/authorize?' . http_build_query([
            'scope' => 'openid', 'response_type' => 'id_token', 'client_id' => $this->tool->client_id,
            'redirect_uri' => $login['target_link_uri'], 'login_hint' => $login['login_hint'], 'nonce' => Str::random(),
        ]))->assertOk();
        preg_match('/name="id_token" value="([^"]+)"/', $response->getContent(), $m);

        return json_decode(base64_decode(strtr(explode('.', $m[1])[1], '-_', '+/')), true);
    }

    private function nrps(string $token, string $query = '', ?int $course = null)
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/lti/platform/nrps/' . ($course ?? $this->course->getKey()) . $query);
    }

    private function token(): string
    {
        return $this->tokenRequest(Lti::SCOPE_NRPS)->assertOk()->json('access_token');
    }

    private function tokenRequest(string $scope)
    {
        return $this->post('/api/lti/platform/token', [
            'grant_type' => 'client_credentials',
            'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
            'client_assertion' => $this->toolKeys->sign([
                'iss' => $this->tool->client_id, 'sub' => $this->tool->client_id,
                'aud' => ['https://lms.example.test/api/lti/platform/token'],
                'iat' => time(), 'exp' => time() + 60, 'jti' => Str::uuid()->toString(),
            ]),
            'scope' => $scope,
        ]);
    }
}
