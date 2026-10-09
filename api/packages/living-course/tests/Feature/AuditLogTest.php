<?php

namespace Ulams\LivingCourse\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Ulams\LivingCourse\Models\AuditEntry;
use Ulams\LivingCourse\Services\AuditLog;
use Ulams\LivingCourse\Tests\TestCase;

class AuditLogTest extends TestCase
{
    private function log(): AuditLog
    {
        return app(AuditLog::class);
    }

    /** Runs $work with the append-only trigger switched off (what only a database owner could do). */
    private function withoutTrigger(callable $work): void
    {
        DB::statement('ALTER TABLE living_course_audit DISABLE TRIGGER USER');
        try {
            $work();
        } finally {
            DB::statement('ALTER TABLE living_course_audit ENABLE TRIGGER USER');
        }
    }

    public function testRowsAreChainedAndTheChainVerifies(): void
    {
        $before = AuditEntry::query()->count();
        $a = $this->log()->record('revision.detected', ['actor_type' => 'system', 'subject_type' => 'revision', 'subject_id' => 'r1', 'data' => ['changed' => 3, 'b' => ['z' => 1, 'a' => 2]]]);
        $b = $this->log()->record('proposal.created', ['actor_type' => 'user', 'actor_id' => 7, 'version_from' => 1, 'version_to' => 2, 'ai_call_ids' => ['c1', 'c2']]);

        $this->assertSame($a->hash, $b->prev_hash);
        $this->assertSame(64, strlen($b->hash));
        $this->assertSame(['changed' => 3, 'b' => ['z' => 1, 'a' => 2]], $a->refresh()->data);
        $this->assertSame(['c1', 'c2'], $b->refresh()->ai_call_ids);
        $this->assertSame($before + 2, AuditEntry::query()->count());
        $verdict = $this->log()->verify();
        $this->assertTrue($verdict['ok'], (string) $verdict['reason']);
        $this->assertSame($before + 2, $verdict['checked']);
    }

    public function testEditingARowBreaksTheChainAtThatRow(): void
    {
        $this->log()->record('revision.detected', ['actor_type' => 'system']);
        $second = $this->log()->record('item.accepted', ['actor_type' => 'user', 'actor_id' => 1, 'data' => ['element' => 'e1']]);
        $this->log()->record('proposal.applied', ['actor_type' => 'user', 'actor_id' => 1]);

        $this->withoutTrigger(fn () => DB::table('living_course_audit')->where('id', $second->id)->update(['actor_id' => 99]));

        $verdict = $this->log()->verify();
        $this->assertFalse($verdict['ok']);
        $this->assertSame($second->id, $verdict['brokenId']);
        $this->assertStringContainsString('changed', (string) $verdict['reason']);
    }

    public function testRemovingARowOrTheTailIsDetected(): void
    {
        $this->log()->record('revision.detected', ['actor_type' => 'system']);
        $middle = $this->log()->record('item.accepted', ['actor_type' => 'system']);
        $last = $this->log()->record('proposal.applied', ['actor_type' => 'system']);

        $this->withoutTrigger(fn () => DB::table('living_course_audit')->where('id', $middle->id)->delete());
        $this->assertSame($last->id, $this->log()->verify()['brokenId']);

        $this->withoutTrigger(fn () => DB::table('living_course_audit')->where('id', $last->id)->delete());
        $verdict = $this->log()->verify();
        $this->assertFalse($verdict['ok']);
        $this->assertStringContainsString('missing', (string) $verdict['reason']);
    }

    public function testThePostgresTriggerRejectsUpdateAndDelete(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('The append-only trigger exists on PostgreSQL only.');
        }
        $row = $this->log()->record('revision.detected', ['actor_type' => 'system']);

        foreach ([
            fn () => DB::table('living_course_audit')->where('id', $row->id)->update(['action' => 'x']),
            fn () => DB::table('living_course_audit')->where('id', $row->id)->delete(),
        ] as $attempt) {
            try {
                DB::transaction($attempt);
                $this->fail('The database accepted a change to the audit trail.');
            } catch (QueryException $e) {
                $this->assertStringContainsString('append-only', $e->getMessage());
            }
        }
        $this->assertTrue($this->log()->verify()['ok']);
    }

    public function testUnknownActionsAreRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->log()->record('proposal.exploded');
    }

    public function testActorIpAndSessionCourseAreFilledIn(): void
    {
        $author = $this->author();
        $session = $this->sessionWithSource($author);
        $session->forceFill(['course_id' => 55])->save();

        $this->actingAs($author, 'api');
        $entry = $this->log()->record('connection.updated', ['session_id' => $session->id, 'ip' => '203.0.113.9']);

        $this->assertSame('user', $entry->actor_type);
        $this->assertSame((int) $author->getKey(), $entry->actor_id);
        $this->assertSame(55, $entry->course_id);
        $this->assertSame('203.0.113.9', $entry->ip);
    }

    public function testBuildingTheInitialRevisionIsAudited(): void
    {
        $session = $this->sessionWithSource($this->author());

        $entry = AuditEntry::query()->where('session_id', $session->id)->where('action', 'connection.created')->firstOrFail();
        $this->assertSame($this->sourceOf($session)->id, $entry->source_id);
        $this->assertSame(1, $entry->data['revision']);
        $this->assertTrue($this->log()->verify()['ok']);
    }
}
