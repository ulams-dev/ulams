<?php

namespace Ulams\Identity\Tests\Feature;

use Ulams\Auth\Models\LoginHandoffState;
use Ulams\Auth\Models\User;
use Ulams\Identity\Models\IdentityAudit;
use Ulams\Identity\Tests\Support\RunsOidcLogin;
use Ulams\Identity\Tests\TestCase;

/** The state of a login: signed, single use, short lived, bound to the provider and the tenant host. */
class StateTest extends TestCase
{
    use RunsOidcLogin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider();
        $this->provider(['slug' => 'other', 'name' => 'Other']);
    }

    public function testAStateWorksOnce(): void
    {
        $this->newVerifier();
        $result = $this->signIn();
        $this->returned($result['response']);

        $replay = $this->answerCallback('acme', $result['state']);

        $replay->assertStatus(400);
        $this->assertSame('state_replayed', IdentityAudit::query()->where('action', 'sso.login_failed')->latest('id')->first()->data['reason']);
        $this->assertSame(1, User::query()->where('email', 'ada@example.com')->count());
        // the second callback did not even reach the provider
        $this->assertCount(1, $this->idp->requestsTo('/token'));
    }

    public function testAnExpiredStateIsRefused(): void
    {
        $auth = $this->queryOf($this->start()->headers->get('Location'));

        $this->travel(11)->minutes();

        $this->answerCallback('acme', $auth['state'])->assertStatus(400);
        $this->assertCount(0, $this->idp->requestsTo('/token'));
    }

    public function testAStateOfAnotherProviderIsRefused(): void
    {
        $auth = $this->queryOf($this->start('acme')->headers->get('Location'));

        $this->answerCallback('other', $auth['state'])->assertStatus(400);
        $this->assertCount(0, $this->idp->requestsTo('/token'));
    }

    public function testAForgedOrMalformedStateIsRefusedAndNotWorthAnAuditRow(): void
    {
        $auth = $this->queryOf($this->start()->headers->get('Location'));
        [$nonce, $exp, $signature] = explode('.', $auth['state']);

        foreach ([$nonce . '.' . $exp . '.' . strrev($signature), $nonce . '.' . ($exp + 10000) . '.' . $signature, 'garbage', '', str_repeat('a', 300)] as $state) {
            $this->answerCallback('acme', $state)->assertStatus(400);
        }
        $this->answerCallback('acme', null)->assertStatus(400);
        $this->assertSame(0, IdentityAudit::query()->where('action', 'sso.login_failed')->count());
        $this->assertCount(0, $this->idp->requestsTo('/token'));
    }

    public function testAStateStartedOnAnotherTenantHostIsRefused(): void
    {
        $auth = $this->queryOf($this->start('acme', [], 'tenant-a.example.test')->headers->get('Location'));

        $this->answerCallback('acme', $auth['state'], ['host' => 'tenant-b.example.test'])->assertStatus(400);
        $this->assertCount(0, $this->idp->requestsTo('/token'));
        $this->assertSame(0, User::query()->where('email', 'ada@example.com')->count());
    }

    public function testTheStateRowKeepsTheVerifierAndNonceEncrypted(): void
    {
        $this->start();

        $raw = \Illuminate\Support\Facades\DB::table('login_handoff_states')->latest('id')->value('data');
        $row = LoginHandoffState::query()->latest('id')->first();

        $this->assertStringNotContainsString($row->data['code_verifier'], $raw);
        $this->assertStringNotContainsString($row->data['nonce'], $raw);
    }
}
