<?php

namespace Ulams\LivingCourse\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Ulams\LivingCourse\Models\AuditEntry;
use Ulams\LivingCourse\Tests\TestCase;

class AuditEndpointsTest extends TestCase
{
    /** @return array{0:\Ulams\Courses\Tests\Models\User,1:\Ulams\CourseBuilder\Models\Session} */
    private function trail(): array
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->actingAs($author, 'api')->post("/api/admin/living-course/sources/{$this->sourceOf($session)->id}/revisions", ['file' => $this->lcFixture('coffee-brewing.v2.md')])->assertCreated();
        $proposal = \Ulams\LivingCourse\Models\Proposal::query()->where('session_id', $session->id)->firstOrFail();
        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/accept-all")->assertOk();
        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/apply")->assertStatus(202);

        return [$author, $session];
    }

    public function testTheTrailTellsWhoDecidedWhatAndWhen(): void
    {
        [$author, $session] = $this->trail();
        $p = '/api/admin/living-course';

        $data = $this->actingAs($author, 'api')->getJson("{$p}/sessions/{$session->id}/audit?perPage=200")->assertOk()->json('data');
        $actions = array_column($data['entries'], 'action');
        foreach (['connection.created', 'revision.detected', 'proposal.created', 'proposal.analysed', 'item.accepted', 'revision.promoted', 'proposal.applied', 'progress.rules_applied'] as $expected) {
            $this->assertContains($expected, $actions, $expected);
        }
        $this->assertSame($data['total'], count($data['entries']));
        $applied = collect($data['entries'])->firstWhere('action', 'proposal.applied');
        $this->assertSame('user', $applied['actor']['type']);
        $this->assertSame((int) $author->getKey(), $applied['actor']['id']);
        $this->assertNotEmpty($applied['actor']['name']);
        $this->assertSame(2, $applied['versionTo'] - $applied['versionFrom'] + 1);
        $this->assertNotNull($applied['revisionId']);
        $this->assertNotEmpty($applied['aiCallIds']);
        $this->assertSame(64, strlen($applied['hash']));
        $this->assertSame('id', array_keys($data['entries'][0])[0]);
        $this->assertGreaterThan($data['entries'][1]['id'], $data['entries'][0]['id'], 'newest first');

        $accepted = $this->actingAs($author, 'api')->getJson("{$p}/sessions/{$session->id}/audit?action=item.&perPage=5")->json('data');
        $this->assertSame(['item.accepted'], array_values(array_unique(array_column($accepted['entries'], 'action'))));
        $this->assertCount(5, $accepted['entries']);
        $this->assertGreaterThan(5, $accepted['total']);
        $this->assertSame(0, $this->actingAs($author, 'api')->getJson("{$p}/sessions/{$session->id}/audit?actorType=agent")->json('data.total'));
        $this->assertSame(0, $this->actingAs($author, 'api')->getJson("{$p}/sessions/{$session->id}/audit?from=2999-01-01")->json('data.total'));
        $this->assertTrue($this->actingAs($author, 'api')->getJson("{$p}/sessions/{$session->id}/audit/verify")->json('data.ok'));
    }

    public function testExportsAsCsvAndJsonAndNeverRunsAsAFormula(): void
    {
        [$author, $session] = $this->trail();
        AuditEntry::query()->where('session_id', $session->id)->count();
        app(\Ulams\LivingCourse\Services\AuditLog::class)->record('connection.updated', ['session_id' => $session->id, 'data' => ['note' => '=HYPERLINK("http://x")'], 'actor_id' => $author->getKey()]);
        $p = "/api/admin/living-course/sessions/{$session->id}/audit/export";

        $csv = $this->actingAs($author, 'api')->get($p)->assertOk();
        $this->assertStringContainsString('text/csv', $csv->headers->get('content-type'));
        $text = $csv->streamedContent();
        $lines = array_filter(explode("\n", $text));
        $this->assertStringStartsWith('id,created_at,action,actor_type,actor_id', $lines[0]);
        $this->assertGreaterThan(20, count($lines));
        $this->assertStringContainsString('proposal.applied', $text);
        $this->assertStringNotContainsString(',=HYPERLINK', $text);
        $this->assertStringNotContainsString('secret', strtolower($text));

        $json = json_decode($this->actingAs($author, 'api')->get($p . '?format=json')->assertOk()->streamedContent(), true);
        $this->assertIsArray($json);
        $this->assertSame(count($lines) - 1, count($json));
        $this->assertSame('connection.created', $json[0]['action']);
        $this->assertSame(64, strlen($json[0]['hash']));
    }

    public function testTenantWideEndpointsAreForAdminsOnly(): void
    {
        [$author] = $this->trail();
        foreach (['/api/admin/living-course/audit/export', '/api/admin/living-course/audit/verify'] as $url) {
            $this->actingAs($author, 'api')->get($url)->assertStatus(403);
            $this->actingAs($this->student(), 'api')->get($url)->assertStatus(403);
            $this->actingAs($this->admin(), 'api')->get($url)->assertOk();
        }
        $this->assertTrue($this->actingAs($this->admin(), 'api')->getJson('/api/admin/living-course/audit/verify')->json('data.ok'));
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/admin/living-course/audit/verify')->assertStatus(401);
    }

    public function testVerificationReportsATamperedRow(): void
    {
        [$author, $session] = $this->trail();
        $row = AuditEntry::query()->where('session_id', $session->id)->where('action', 'item.accepted')->firstOrFail();
        DB::statement('ALTER TABLE living_course_audit DISABLE TRIGGER USER');
        DB::table('living_course_audit')->where('id', $row->id)->update(['actor_id' => 999]);
        DB::statement('ALTER TABLE living_course_audit ENABLE TRIGGER USER');

        $verdict = $this->actingAs($author, 'api')->getJson("/api/admin/living-course/sessions/{$session->id}/audit/verify")->assertOk()->json('data');

        $this->assertFalse($verdict['ok']);
        $this->assertSame($row->id, $verdict['brokenId']);
    }

    public function testSessionAuditIsGuardedLikeTheRest(): void
    {
        [$author, $session] = $this->trail();
        $urls = ["audit", "audit/export", "audit/verify"];
        foreach ($urls as $u) {
            $url = "/api/admin/living-course/sessions/{$session->id}/{$u}";
            $this->actingAs($author, 'api')->get($url)->assertOk();
            $this->actingAs($this->admin(), 'api')->get($url)->assertOk();
            $this->actingAs($this->tutor(), 'api')->get($url)->assertStatus(403);
            $this->actingAs($this->student(), 'api')->get($url)->assertStatus(403);
            $this->actingAs($author, 'api')->get("/api/admin/living-course/sessions/01aaaaaaaaaaaaaaaaaaaaaaaa/{$u}")->assertStatus(404);
        }
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/admin/living-course/sessions/{$session->id}/audit")->assertStatus(401);
    }
}
