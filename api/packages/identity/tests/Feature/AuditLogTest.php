<?php

namespace Ulams\Identity\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Ulams\Identity\Models\IdentityAudit;
use Ulams\Identity\Services\IdentityAuditLog;
use Ulams\Identity\Tests\TestCase;

class AuditLogTest extends TestCase
{
    private IdentityAuditLog $log;

    protected function setUp(): void
    {
        parent::setUp();
        $this->log = new IdentityAuditLog();
    }

    private function fill(): void
    {
        $this->log->record('sso.login', ['actor' => 'idp', 'subject_type' => 'user', 'subject_id' => 7, 'data' => ['b' => 2, 'a' => ['y' => 1, 'x' => 2]]]);
        $this->log->record('sso.login_failed', ['actor' => 'idp', 'data' => ['reason' => 'expired']]);
        $this->log->record('provider.created', ['actor' => 'system']);
    }

    public function testRowsAreChainedAndTheChainVerifies(): void
    {
        $this->assertSame(['ok' => true, 'checked' => 0, 'broken_id' => null, 'reason' => null], $this->log->verify());
        $this->fill();

        $rows = IdentityAudit::query()->orderBy('id')->get();
        $this->assertSame(str_repeat('0', 64), $rows[0]->prev_hash);
        $this->assertSame($rows[0]->hash, $rows[1]->prev_hash);
        $this->assertSame($rows[1]->hash, $rows[2]->prev_hash);
        $this->assertSame(['ok' => true, 'checked' => 3, 'broken_id' => null, 'reason' => null], $this->log->verify());
    }

    public function testOnlyKnownActionsAreAccepted(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->log->record('sso.something_else');
    }

    public function testAnEditedRowBreaksTheChain(): void
    {
        $this->skipUnlessPgsql();
        $this->fill();
        $middle = IdentityAudit::query()->orderBy('id')->get()[1];
        $this->withoutImmutabilityTrigger();

        DB::table('identity_audit')->where('id', $middle->id)->update(['data' => json_encode(['reason' => 'nothing happened'])]);

        $result = $this->log->verify();
        $this->assertFalse($result['ok']);
        $this->assertSame($middle->id, $result['broken_id']);
        $this->assertSame(1, $result['checked']);
    }

    public function testARemovedRowBreaksTheChain(): void
    {
        $this->skipUnlessPgsql();
        $this->fill();
        $middle = IdentityAudit::query()->orderBy('id')->get()[1];
        $this->withoutImmutabilityTrigger();

        DB::table('identity_audit')->where('id', $middle->id)->delete();

        $this->assertFalse($this->log->verify()['ok']);
    }

    public function testTruncatingTheTailIsDetectedByTheHead(): void
    {
        $this->skipUnlessPgsql();
        $this->fill();
        $last = IdentityAudit::query()->orderByDesc('id')->first();
        $this->withoutImmutabilityTrigger();

        DB::table('identity_audit')->where('id', $last->id)->delete();

        $result = $this->log->verify();
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('missing', $result['reason']);
    }

    public function testTheDatabaseRefusesUpdatesAndDeletes(): void
    {
        $this->skipUnlessPgsql();
        $this->fill();

        foreach ([
            fn () => DB::table('identity_audit')->update(['action' => 'x']),
            fn () => DB::table('identity_audit')->delete(),
            fn () => DB::unprepared('TRUNCATE identity_audit'),
        ] as $attempt) {
            DB::beginTransaction();
            try {
                $attempt();
                $this->fail('The append-only trigger did not fire.');
            } catch (\Illuminate\Database\QueryException $e) {
                $this->assertStringContainsString('append-only', $e->getMessage());
            } finally {
                DB::rollBack();
            }
        }
        $this->assertSame(3, IdentityAudit::query()->count());
    }

    public function testTheHashDoesNotDependOnTheOrderOfKeys(): void
    {
        $row = ['actor' => 'idp', 'actor_id' => null, 'provider_id' => null, 'action' => 'sso.login', 'subject_type' => null, 'subject_id' => null,
            'ip' => null, 'user_agent' => null, 'request_id' => null, 'created_at' => '2026-10-26 10:00:00.000000'];

        $a = IdentityAuditLog::hashOf('p', $row + ['data' => ['b' => 1, 'a' => 2]]);
        $b = IdentityAuditLog::hashOf('p', $row + ['data' => ['a' => 2, 'b' => 1]]);

        $this->assertSame($a, $b);
        $this->assertNotSame($a, IdentityAuditLog::hashOf('q', $row + ['data' => ['a' => 2, 'b' => 1]]));
    }

    private function skipUnlessPgsql(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('The append-only trigger is PostgreSQL only (as in ADR 0034).');
        }
    }

    /** Inside the test transaction: DDL is transactional in PostgreSQL, so this is undone by the rollback. */
    private function withoutImmutabilityTrigger(): void
    {
        DB::unprepared('ALTER TABLE identity_audit DISABLE TRIGGER identity_audit_no_change');
    }
}
