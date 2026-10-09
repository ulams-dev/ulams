<?php

namespace Ulams\Lti\Tests\Unit;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Carbon;
use Ulams\Lti\Models\LtiKey;
use Ulams\Lti\Services\KeyService;
use Ulams\Lti\Tests\TestCase;

class KeyServiceTest extends TestCase
{
    public function testEnsureKeysCreatesAnActiveAndANextKeyOnce(): void
    {
        $keys = app(KeyService::class);
        $keys->ensureKeys();
        $keys->ensureKeys();

        $this->assertSame(1, LtiKey::query()->where('status', LtiKey::ACTIVE)->count());
        $this->assertSame(1, LtiKey::query()->where('status', LtiKey::NEXT)->count());
        $this->assertCount(2, $keys->jwks()['keys']);
    }

    public function testPrivateKeysAreEncryptedAtRestAndNotPublished(): void
    {
        $keys = app(KeyService::class);
        $keys->ensureKeys();

        $raw = \DB::table('lti_keys')->value('private_key');
        $this->assertStringNotContainsString('PRIVATE KEY', $raw);
        $this->assertStringNotContainsString('PRIVATE KEY', json_encode($keys->jwks()));
        foreach ($keys->jwks()['keys'] as $jwk) {
            $this->assertSame(['kty', 'alg', 'use', 'kid', 'n', 'e'], array_keys($jwk));
        }
    }

    public function testSignedTokensVerifyWithThePublishedKeySet(): void
    {
        $keys = app(KeyService::class);
        $token = $keys->sign(['sub' => 'x', 'exp' => time() + 60]);

        $claims = JWT::decode($token, JWK::parseKeySet($keys->jwks(), 'RS256'));

        $this->assertSame('x', $claims->sub);
        $this->assertSame($keys->activeKey()->kid, JWT::jsonDecode(JWT::urlsafeB64Decode(explode('.', $token)[0]))->kid);
    }

    public function testRotationPromotesTheNextKeyAndKeepsTheOldOneDuringTheGracePeriod(): void
    {
        $keys = app(KeyService::class);
        $keys->ensureKeys();
        $oldActive = $keys->activeKey()->kid;
        $oldNext = LtiKey::query()->where('status', LtiKey::NEXT)->value('kid');
        $tokenBefore = $keys->sign(['sub' => 'before', 'exp' => time() + 60]);

        $result = $keys->rotate();

        $this->assertSame($oldNext, $result['activated']);
        $this->assertSame($oldNext, $keys->activeKey()->kid);
        $published = array_column($keys->jwks()['keys'], 'kid');
        $this->assertContains($oldActive, $published, 'retired key stays published during the grace period');
        $this->assertContains($result['next'], $published);
        // tokens signed before the rotation still verify
        $this->assertSame('before', JWT::decode($tokenBefore, JWK::parseKeySet($keys->jwks(), 'RS256'))->sub);

        Carbon::setTestNow(Carbon::now()->addDays(31));
        $keys->rotate();
        Carbon::setTestNow();

        $this->assertNull(LtiKey::query()->where('kid', $oldActive)->first(), 'retired keys are deleted after the grace period');
    }

    public function testRotateCommand(): void
    {
        $this->artisan('ulams:lti:rotate-keys', ['--init' => true])->assertExitCode(0);
        $active = app(KeyService::class)->activeKey()->kid;

        $this->artisan('ulams:lti:rotate-keys')->assertExitCode(0);

        $this->assertNotSame($active, app(KeyService::class)->activeKey()->kid);
    }
}
