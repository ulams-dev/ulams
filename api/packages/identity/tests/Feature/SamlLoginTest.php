<?php

namespace Ulams\Identity\Tests\Feature;

use Ulams\Identity\Models\IdentityAudit;
use Ulams\Identity\Models\IdentitySession;
use Ulams\Identity\Models\SamlReplay;
use Ulams\Identity\Tests\Support\FakeSamlIdp;
use Ulams\Identity\Tests\Support\RunsOidcLogin;
use Ulams\Identity\Tests\Support\RunsSamlLogin;
use Ulams\Identity\Tests\TestCase;

class SamlLoginTest extends TestCase
{
    use RunsOidcLogin;
    use RunsSamlLogin;

    public function testASamlSignInCreatesAStudentAndHandsTheBrowserAOneTimeCode(): void
    {
        $this->samlProvider();
        $this->newVerifier();

        $result = $this->samlSignIn();

        $params = $this->returned($result['response']);
        $this->assertSame('true', $params['complete']);
        $this->assertArrayNotHasKey('error', $params);
        $this->assertDoesNotMatchRegularExpression('/eyJ/', (string) $result['response']->headers->get('Location'));

        $exchange = $this->exchange($params['code'])->assertOk();
        $this->assertNotEmpty($exchange->json('data.token'));

        $user = \Ulams\Auth\Models\User::query()->where('email', 'ada@example.com')->firstOrFail();
        $this->assertSame('Ada', $user->first_name);
        $this->assertTrue($user->hasRole('student'));
        $this->assertNotNull($user->email_verified_at);

        $session = IdentitySession::query()->firstOrFail();
        $this->assertSame('user-1', $session->name_id);
        $this->assertSame('_session-1', $session->session_index);
        $this->assertSame($user->id, $session->user_id);

        $login = IdentityAudit::query()->where('action', 'sso.login')->firstOrFail();
        $this->assertSame('saml', $login->data['protocol']);
    }
}
