<?php

namespace Ulams\Lti\Tests\Unit;

use Illuminate\Support\Carbon;
use Ulams\Lti\Exceptions\LtiRequestException;
use Ulams\Lti\Platform\RoleMapper;
use Ulams\Lti\Services\NonceStore;
use Ulams\Lti\Support\HintSigner;
use Ulams\Lti\Support\Lti;
use Ulams\Lti\Support\SafeHttp;
use Ulams\Lti\Tests\TestCase;

class NonceAndHintTest extends TestCase
{
    public function testNoncesAreSingleUseAndExpire(): void
    {
        $store = app(NonceStore::class);

        $this->assertTrue($store->remember(NonceStore::JTI, 'abc', 60));
        $this->assertFalse($store->remember(NonceStore::JTI, 'abc', 60), 'replay is refused');
        $this->assertTrue($store->remember(NonceStore::NONCE, 'abc', 60), 'types are separate');

        $store->remember(NonceStore::CODE, 'code-1', 60, ['uid' => 7]);
        $this->assertSame(['uid' => 7], $store->take(NonceStore::CODE, 'code-1'));
        $this->assertNull($store->take(NonceStore::CODE, 'code-1'), 'taken once');

        $store->remember(NonceStore::CODE, 'code-2', 60);
        Carbon::setTestNow(Carbon::now()->addMinutes(2));
        $this->assertNull($store->take(NonceStore::CODE, 'code-2'), 'expired');
        $this->assertTrue($store->remember(NonceStore::JTI, 'abc', 60), 'an expired value can be recorded again');
        Carbon::setTestNow();
    }

    public function testHintsCannotBeForgedReusedForAnotherPurposeOrExpire(): void
    {
        $hints = app(HintSigner::class);
        $token = $hints->sign('login_hint', ['uid' => 1], 60);

        $this->assertSame(1, $hints->verify('login_hint', $token)['uid']);
        $this->assertRejected(fn () => $hints->verify('deep_link_data', $token));
        $this->assertRejected(fn () => $hints->verify('login_hint', $token . 'x'));
        $this->assertRejected(fn () => $hints->verify('login_hint', $hints->sign('login_hint', ['uid' => 1], -10)));
        $this->assertRejected(fn () => $hints->verify('login_hint', null));
    }

    public function testTenantIsolationHintsOfAnotherTenantAreRejected(): void
    {
        $token = app(HintSigner::class)->sign('login_hint', ['uid' => 1, 'tool' => 1, 'topic' => 1], 60);

        config(['app.key' => 'base64:' . base64_encode(random_bytes(32))]);

        $this->assertRejected(fn () => app(HintSigner::class)->verify('login_hint', $token));
    }

    public function testRoleMapping(): void
    {
        $mapper = new RoleMapper();

        $this->assertSame('tutor', $mapper->ulamsRole([Lti::ROLE_INSTRUCTOR]));
        $this->assertSame('student', $mapper->ulamsRole([Lti::ROLE_LEARNER]));
        $this->assertSame('student', $mapper->ulamsRole([Lti::ROLE_ADMINISTRATOR]), 'nobody becomes an administrator through LTI');
        $this->assertSame('student', $mapper->ulamsRole([]));
    }

    public function testOutgoingUrlsMustBePublicHttps(): void
    {
        config(['ulams_lti.allow_insecure_urls' => false]);

        foreach ([
            'http://tool.example.com/jwks',
            'https://127.0.0.1/jwks',
            'https://10.0.0.5/jwks',
            'https://169.254.169.254/latest/meta-data',
            'https://[::1]/jwks',
            'ftp://tool.example.com/jwks',
        ] as $url) {
            $this->assertRejected(fn () => SafeHttp::check($url), $url);
        }

        $this->assertSame('1.1.1.1:443:1.1.1.1', SafeHttp::check('https://1.1.1.1/jwks'));

        config(['ulams_lti.allow_insecure_urls' => true]);
        $this->assertNull(SafeHttp::check('http://moodle:8080/jwks'));
    }

    private function assertRejected(callable $callback, string $message = ''): void
    {
        try {
            $callback();
        } catch (LtiRequestException) {
            $this->addToAssertionCount(1);

            return;
        }
        $this->fail('Expected a rejection. ' . $message);
    }
}
