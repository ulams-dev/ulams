<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Bridge\AccessTokenRepository;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResourceServer;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Variant;
use Ulams\CourseBuilder\Tests\TestCase;

/**
 * Isolation of the variant endpoints (ADR 0192). Between tenants (separate databases and Passport keys, ADR 0007) a token of
 * another tenant is rejected; inside a tenant another author gets 403 and an unknown or foreign id never changes anything.
 * The shared per-endpoint matrix (tutor 403, student 403, guest 401, unknown 404) is in IsolationTest.
 */
class VariantIsolationTest extends TestCase
{
    private const P = '/api/admin/course-builder';

    private function sessionWithVariant(): array
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $variant = Variant::query()->create(['session_id' => $session->id, 'key' => 'expert', 'label' => 'Expert', 'level' => 'expert', 'audience_brief' => ['level' => 'expert'], 'status' => Variant::DRAFT]);

        return [$author, $session, $variant];
    }

    public function testATokenOfAnotherTenantIsRejectedOnEveryVariantEndpoint(): void
    {
        [$author, $session, $variant] = $this->sessionWithVariant();
        $token = $author->createToken('test')->accessToken;
        $call = function (string $method, string $uri, array $body = []) use ($token) {
            $res = $this->json($method, $uri, $body, ['Authorization' => 'Bearer ' . $token]);
            Auth::forgetGuards();

            return $res;
        };
        $call('GET', self::P . "/sessions/{$session->id}/variants")->assertOk();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        config(['passport.private_key' => $private, 'passport.public_key' => openssl_pkey_get_details($key)['key']]);
        foreach ([AuthorizationServer::class, ResourceServer::class, AccessTokenRepository::class] as $service) {
            $this->app->forgetInstance($service);
        }
        Auth::forgetGuards();

        foreach ([
            ['GET', self::P . "/sessions/{$session->id}/variants", []],
            ['POST', self::P . "/sessions/{$session->id}/variants", ['audiences' => [['level' => 'beginner']]]],
            ['PATCH', self::P . "/variants/{$variant->id}", ['label' => 'Hijacked']],
            ['DELETE', self::P . "/variants/{$variant->id}", []],
            ['POST', self::P . "/sessions/{$session->id}/runs", ['messages' => [], 'forwardedProps' => ['stage' => 'variants']]],
        ] as [$method, $uri, $body]) {
            $call($method, $uri, $body)->assertUnauthorized();
        }
        $this->assertSame('Expert', $variant->refresh()->label);
        $this->assertSame(Variant::DRAFT, $variant->status);
        $this->assertSame(1, Variant::query()->count());
    }

    public function testAnotherAuthorCannotReadChangeOrRetireAVariant(): void
    {
        [, $session, $variant] = $this->sessionWithVariant();
        $other = $this->tutor();

        $this->actingAs($other, 'api')->getJson(self::P . "/sessions/{$session->id}/variants")->assertStatus(403);
        $this->actingAs($other, 'api')->postJson(self::P . "/sessions/{$session->id}/variants", ['audiences' => [['level' => 'beginner']]])->assertStatus(403);
        $this->actingAs($other, 'api')->patchJson(self::P . "/variants/{$variant->id}", ['label' => 'Hijacked'])->assertStatus(403);
        $this->actingAs($other, 'api')->deleteJson(self::P . "/variants/{$variant->id}")->assertStatus(403);
        $this->assertSame('Expert', $variant->refresh()->label);
        $this->assertSame(Variant::DRAFT, $variant->status);
        $this->assertSame(1, Variant::query()->count());
    }

    public function testAnAdminCanLookAtVariantsButNotChangeThem(): void
    {
        [, $session, $variant] = $this->sessionWithVariant();
        $admin = $this->admin();

        $this->actingAs($admin, 'api')->getJson(self::P . "/sessions/{$session->id}/variants")->assertOk()->assertJsonPath('data.variants.0.key', 'expert');
        $this->actingAs($admin, 'api')->patchJson(self::P . "/variants/{$variant->id}", ['label' => 'Admin edit'])->assertStatus(403);
        $this->actingAs($admin, 'api')->deleteJson(self::P . "/variants/{$variant->id}")->assertStatus(403);
        $this->assertSame('Expert', $variant->refresh()->label);
    }

    public function testAVariantVersionOfAnotherSessionNeverResolvesThroughMine(): void
    {
        [$author, $session, $variant] = $this->sessionWithVariant();
        $mine = Session::query()->create(['author_id' => $author->getKey()]);

        // the variant belongs to its own session: acting on it through the author's other session is not possible
        $this->actingAs($author, 'api')->getJson(self::P . "/sessions/{$mine->id}/variants")->assertOk()->assertJsonCount(0, 'data.variants');
        $this->assertSame($session->id, $variant->session_id);
    }
}
