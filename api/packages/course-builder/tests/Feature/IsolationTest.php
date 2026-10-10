<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Ulams\CourseBuilder\Models\Run;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Step;
use Ulams\CourseBuilder\Tests\TestCase;

/**
 * Author isolation inside a tenant for every endpoint: another tutor gets 403, a student 403,
 * a guest 401, unknown ids 404. Tenant isolation comes from the database per tenant: an id from
 * another tenant is an unknown id here (404); the cross-tenant HTTP check is in
 * packages/tenancy/tests/Integration/TenantIsolationTest (opt-in, real tenants).
 */
class IsolationTest extends TestCase
{
    /** @return array<int,array{0:string,1:string}> method, uri */
    private function endpoints(Session $s): array
    {
        $source = $s->sources()->first();
        $fragment = $source->fragments()->first();
        $version = $s->currentVersion;
        $run = Run::query()->where('session_id', $s->id)->first();
        $step = Step::query()->whereIn('run_id', Run::query()->where('session_id', $s->id)->select('id'))->first();
        $p = '/api/admin/course-builder';

        return [
            ['GET', "{$p}/sessions/{$s->id}"],
            ['DELETE', "{$p}/sessions/{$s->id}"],
            ['POST', "{$p}/sessions/{$s->id}/sources"],
            ['GET', "{$p}/sessions/{$s->id}/sources/{$source->id}"],
            ['GET', "{$p}/sessions/{$s->id}/citations"],
            ['GET', "{$p}/sessions/{$s->id}/critiques"],
            ['GET', "{$p}/fragments/{$fragment->id}"],
            ['GET', "{$p}/sessions/{$s->id}/brief"],
            ['PUT', "{$p}/sessions/{$s->id}/brief"],
            ['POST', "{$p}/sessions/{$s->id}/runs"],
            ['GET', "{$p}/sessions/{$s->id}/events"],
            ['GET', "{$p}/runs/{$run->id}"],
            ['POST', "{$p}/runs/{$run->id}/cancel"],
            ['POST', "{$p}/runs/{$run->id}/steps/" . ($step?->id ?? '01aaaaaaaaaaaaaaaaaaaaaaaa') . '/retry'],
            ['GET', "{$p}/sessions/{$s->id}/versions"],
            ['GET', "{$p}/versions/{$version->id}"],
            ['GET', "{$p}/versions/{$version->id}/diff"],
            ['POST', "{$p}/versions/{$version->id}/approve"],
            ['POST', "{$p}/versions/{$version->id}/reject"],
            ['POST', "{$p}/versions/{$version->id}/restore"],
            ['POST', "{$p}/sessions/{$s->id}/undo"],
            ['POST', "{$p}/sessions/{$s->id}/redo"],
            ['POST', "{$p}/sessions/{$s->id}/outline"],
            ['POST', "{$p}/sessions/{$s->id}/global-edit"],
            ['POST', "{$p}/sessions/{$s->id}/elements/{$s->currentVersion->document['modules'][0]['id']}/variants"],
            ['POST', "{$p}/sessions/{$s->id}/apply"],
            ['GET', "{$p}/sessions/{$s->id}/publish-check"],
            ['POST', "{$p}/sessions/{$s->id}/new-site"],
            ['POST', "{$p}/sessions/{$s->id}/publish"],
            ['GET', "{$p}/sessions/{$s->id}/usage"],
        ];
    }

    public function testAnotherTutorIsForbiddenOnEveryEndpoint(): void
    {
        $owner = $this->author();
        $session = $this->toApplyReview($owner);
        $other = $this->tutor();

        foreach ($this->endpoints($session) as [$method, $uri]) {
            $this->actingAs($other, 'api')->json($method, $uri)->assertStatus(403);
        }
        $this->assertSame([], $this->actingAs($other, 'api')->getJson('/api/admin/course-builder/sessions')->json('data'));
        $this->assertNotNull(Session::query()->find($session->id));
    }

    public function testStudentsAndGuestsAreRefused(): void
    {
        $owner = $this->author();
        $session = $this->toApplyReview($owner);
        $student = $this->student();
        foreach ($this->endpoints($session) as [$method, $uri]) {
            $this->actingAs($student, 'api')->json($method, $uri)->assertStatus(403);
        }
        $this->actingAs($student, 'api')->postJson('/api/admin/course-builder/sessions')->assertStatus(403);
        $this->app['auth']->forgetGuards();
        foreach ($this->endpoints($session) as [$method, $uri]) {
            $this->json($method, $uri)->assertStatus(401);
        }
    }

    public function testAdminsCanLookButNotAct(): void
    {
        $owner = $this->author();
        $session = $this->toApplyReview($owner);
        $admin = $this->admin();
        $this->actingAs($admin, 'api')->getJson("/api/admin/course-builder/sessions/{$session->id}")->assertOk();
        $this->actingAs($admin, 'api')->postJson("/api/admin/course-builder/sessions/{$session->id}/apply")->assertStatus(403);
    }

    public function testUnknownIdsAreNotFound(): void
    {
        $author = $this->author();
        $p = '/api/admin/course-builder';
        $missing = '01aaaaaaaaaaaaaaaaaaaaaaaa';
        foreach ([
            ['GET', "{$p}/sessions/{$missing}"],
            ['GET', "{$p}/fragments/frg_aaaaaaaaaaaa"],
            ['GET', "{$p}/versions/{$missing}"],
            ['GET', "{$p}/runs/{$missing}"],
            ['GET', "{$p}/runs/not-an-id"],
            ['POST', "{$p}/runs/{$missing}/cancel"],
            ['GET', "{$p}/sessions/not-an-id/events"],
        ] as [$method, $uri]) {
            $this->actingAs($author, 'api')->json($method, $uri)->assertStatus(404);
        }
        // a source of another session never resolves through mine
        $other = $this->uploaded($this->tutor());
        $mine = $this->newSession($author);
        $this->actingAs($author, 'api')->getJson("{$p}/sessions/{$mine->id}/sources/{$other->sources()->first()->id}")->assertStatus(404);
    }

    public function testDisabledAiAnswers503(): void
    {
        config(['ai.driver' => 'disabled']);
        $this->app->forgetInstance(\Ulams\Ai\Contracts\LlmClient::class);
        $this->app->forgetInstance(\Ulams\Ai\Contracts\LlmDriver::class);
        $this->actingAs($this->author(), 'api')->getJson('/api/admin/course-builder/sessions')->assertStatus(503)->assertJsonPath('code', 'ai_disabled');
    }
}
