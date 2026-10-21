<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Bridge\AccessTokenRepository;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResourceServer;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Tests\TestCase;

/**
 * Isolation of the course-as-code endpoints (ADR 0160), with AI off. Inside a tenant: another tutor gets 403,
 * a student 403, a guest 401, unknown ids 404. Between tenants (separate databases and Passport keys, ADR 0007):
 * a token of another tenant is rejected, and the real two-tenant HTTP check is in
 * packages/tenancy/tests/Integration/TenantIsolationTest (opt-in).
 */
class CourseAsCodeIsolationTest extends TestCase
{
    private const P = '/api/admin/course-as-code';

    private function applied(): Session
    {
        $session = $this->toApplied($this->author());
        config(['ai.driver' => 'disabled']);
        $this->app->forgetInstance(\Ulams\Ai\Contracts\LlmClient::class);
        $this->app->forgetInstance(\Ulams\Ai\Contracts\LlmDriver::class);

        return $session;
    }

    /** @return array<int,array{0:string,1:string,2:array}> */
    private function endpoints(Session $s): array
    {
        $doc = $s->currentVersion->document;

        return [
            ['GET', self::P . "/courses/{$s->course_id}/blueprint", []],
            ['POST', self::P . "/sessions/{$s->id}/imports", ['document' => $doc, 'baseVersionId' => $s->current_version_id]],
            ['GET', self::P . "/versions/{$s->current_version_id}/diff", []],
            ['POST', self::P . "/versions/{$s->current_version_id}/approve", []],
            ['POST', self::P . "/sessions/{$s->id}/apply", []],
            ['POST', self::P . "/sessions/{$s->id}/publish", []],
        ];
    }

    public function testAnotherTutorIsForbiddenOnEveryEndpoint(): void
    {
        $session = $this->applied();
        $other = $this->tutor();
        $versions = Version::query()->where('session_id', $session->id)->count();
        foreach ($this->endpoints($session) as [$method, $uri, $body]) {
            $this->actingAs($other, 'api')->json($method, $uri, $body)->assertStatus(403);
        }
        $this->assertSame($versions, Version::query()->where('session_id', $session->id)->count(), 'a forbidden import stores nothing');
    }

    public function testStudentsAndGuestsAreRefused(): void
    {
        $session = $this->applied();
        $student = $this->student();
        foreach ($this->endpoints($session) as [$method, $uri, $body]) {
            $this->actingAs($student, 'api')->json($method, $uri, $body)->assertStatus(403);
        }
        $this->app['auth']->forgetGuards();
        foreach ($this->endpoints($session) as [$method, $uri, $body]) {
            $this->json($method, $uri, $body)->assertStatus(401);
        }
    }

    public function testAdminsCanReadButNotPushOrApprove(): void
    {
        $session = $this->applied();
        $admin = $this->admin();
        $this->actingAs($admin, 'api')->getJson(self::P . "/courses/{$session->course_id}/blueprint")->assertOk();
        $this->actingAs($admin, 'api')->postJson(self::P . "/sessions/{$session->id}/imports", ['document' => $session->currentVersion->document, 'baseVersionId' => $session->current_version_id])->assertStatus(403);
        $this->actingAs($admin, 'api')->postJson(self::P . "/sessions/{$session->id}/apply")->assertStatus(403);
    }

    public function testUnknownIdsAreNotFound(): void
    {
        $author = $this->author();
        $missing = '01aaaaaaaaaaaaaaaaaaaaaaaa';
        foreach ([
            ['GET', self::P . '/courses/987654321/blueprint', []],
            ['POST', self::P . "/sessions/{$missing}/imports", ['document' => [], 'baseVersionId' => $missing]],
            ['POST', self::P . '/sessions/not-an-id/imports', []],
            ['GET', self::P . "/versions/{$missing}/diff", []],
            ['POST', self::P . "/versions/{$missing}/approve", []],
            ['POST', self::P . "/sessions/{$missing}/apply", []],
            ['POST', self::P . "/sessions/{$missing}/publish", []],
        ] as [$method, $uri, $body]) {
            $this->actingAs($author, 'api')->json($method, $uri, $body)->assertStatus(404);
        }
        // a version of another author's session never resolves through mine
        $theirs = $this->applied();
        $mine = Session::query()->create(['author_id' => $author->getKey()]);
        $this->actingAs($author, 'api')->postJson(self::P . "/sessions/{$mine->id}/imports", ['document' => $theirs->currentVersion->document, 'baseVersionId' => $theirs->current_version_id])
            ->assertStatus(409)->assertJsonPath('code', 'NO_BLUEPRINT');
    }

    public function testATokenOfAnotherTenantIsRejected(): void
    {
        $session = $this->applied();
        $token = $session->author->createToken('test')->accessToken;
        $call = function () use ($token, $session) {
            $res = $this->getJson(self::P . "/courses/{$session->course_id}/blueprint", ['Authorization' => 'Bearer ' . $token]);
            Auth::forgetGuards();

            return $res;
        };
        $call()->assertOk();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        config(['passport.private_key' => $private, 'passport.public_key' => openssl_pkey_get_details($key)['key']]);
        foreach ([AuthorizationServer::class, ResourceServer::class, AccessTokenRepository::class] as $service) {
            $this->app->forgetInstance($service);
        }
        Auth::forgetGuards();

        $call()->assertUnauthorized();
        $this->withHeaders(['Authorization' => 'Bearer ' . $token])->postJson(self::P . "/sessions/{$session->id}/imports", ['document' => [], 'baseVersionId' => $session->current_version_id])->assertUnauthorized();
    }
}
