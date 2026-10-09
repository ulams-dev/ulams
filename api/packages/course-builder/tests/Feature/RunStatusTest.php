<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Ulams\CourseBuilder\Models\Run;
use Ulams\CourseBuilder\Models\Step;
use Ulams\CourseBuilder\Tests\TestCase;

/** GET /api/admin/course-builder/runs/{run}: the poll target of `--wait` (docs/plans/cli.md 6.8). */
class RunStatusTest extends TestCase
{
    public function testReturnsTheDocumentedShape(): void
    {
        $author = $this->author();
        $session = $this->toApplyReview($author);
        $run = Run::query()->where('session_id', $session->id)->where('kind', 'generate')->firstOrFail();

        $data = $this->actingAs($author, 'api')->getJson("/api/admin/course-builder/runs/{$run->id}")
            ->assertOk()->assertJsonPath('success', true)->json('data');

        $this->assertSame($run->id, $data['id']);
        $this->assertSame($session->id, $data['sessionId']);
        $this->assertSame('generate', $data['kind']);
        $this->assertContains($data['status'], ['queued', 'running', 'succeeded', 'failed', 'cancelled']);
        $this->assertSame('succeeded', $data['status']);
        $this->assertNotEmpty($data['steps']);
        $this->assertSame(['id', 'name', 'status', 'error'], array_keys($data['steps'][0]));
        $this->assertContains($data['steps'][0]['status'], ['queued', 'running', 'succeeded', 'failed']);
        $this->assertArrayHasKey('startedAt', $data);
        $this->assertArrayHasKey('finishedAt', $data);
        $this->assertArrayHasKey('error', $data);
    }

    public function testFailedStepsAndCancelledRunsAreReported(): void
    {
        $author = $this->author();
        $session = $this->toApplyReview($author);
        $run = Run::query()->where('session_id', $session->id)->where('kind', 'generate')->firstOrFail();
        $step = Step::query()->where('run_id', $run->id)->firstOrFail();
        $step->forceFill(['status' => 'failed', 'error' => 'boom'])->save();
        $run->forceFill(['status' => 'failed', 'error' => 'A step failed.'])->save();

        $data = $this->actingAs($author, 'api')->getJson("/api/admin/course-builder/runs/{$run->id}")->assertOk()->json('data');
        $this->assertSame('failed', $data['status']);
        $this->assertSame('A step failed.', $data['error']);
        $failed = collect($data['steps'])->firstWhere('id', $step->id);
        $this->assertSame(['failed', 'boom'], [$failed['status'], $failed['error']]);

        $run->forceFill(['status' => 'needs_attention', 'error' => null])->save();
        $data = $this->actingAs($author, 'api')->getJson("/api/admin/course-builder/runs/{$run->id}")->json('data');
        $this->assertSame(['running', true], [$data['status'], $data['needsAttention']]);

        $run->forceFill(['status' => 'cancelled'])->save();
        $this->assertSame('cancelled', $this->actingAs($author, 'api')->getJson("/api/admin/course-builder/runs/{$run->id}")->json('data.status'));
    }

    public function testAnotherAuthorIsForbiddenAdminsCanLookAndGuestsAreRefused(): void
    {
        $owner = $this->author();
        $session = $this->toApplyReview($owner);
        $run = Run::query()->where('session_id', $session->id)->firstOrFail();
        $uri = "/api/admin/course-builder/runs/{$run->id}";

        $this->actingAs($this->tutor(), 'api')->getJson($uri)->assertStatus(403);
        $this->actingAs($this->student(), 'api')->getJson($uri)->assertStatus(403);
        $this->actingAs($this->admin(), 'api')->getJson($uri)->assertOk();
        $this->app['auth']->forgetGuards();
        $this->getJson($uri)->assertStatus(401);
    }

    public function testARunOfAnotherTenantIsAnUnknownId(): void
    {
        // Database per tenant: an id minted on another tenant never exists here, so it is a 404,
        // not a leak. The opt-in cross-host check lives in packages/tenancy/tests/Integration.
        $this->actingAs($this->author(), 'api')->getJson('/api/admin/course-builder/runs/01zzzzzzzzzzzzzzzzzzzzzzzz')->assertStatus(404);
    }
}
