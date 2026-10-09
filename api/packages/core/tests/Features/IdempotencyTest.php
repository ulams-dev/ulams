<?php

namespace Ulams\Core\Tests\Features;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Ulams\Core\Http\Middleware\Idempotency;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Core\Tests\TestCase;

/** `Idempotency-Key` and `X-Request-Id` (ADR 0074, docs/plans/cli.md 6.4). */
class IdempotencyTest extends TestCase
{
    use CreatesUsers, DatabaseTransactions;

    public static int $calls = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withMiddleware();
        Cache::flush();
        self::$calls = 0;
        Route::middleware('auth:api')->post('api/zz/idem', function () {
            self::$calls++;

            return response()->json(['n' => self::$calls, 'success' => true], 201, ['Location' => '/api/zz/idem/1']);
        });
        Route::middleware('auth:api')->post('api/zz/idem-fail', function () {
            self::$calls++;

            return response()->json(['n' => self::$calls], self::$calls === 1 ? 500 : 200);
        });
        Route::middleware('auth:api')->post('api/zz/idem-forbidden', function () {
            self::$calls++;

            return response()->json(['n' => self::$calls], self::$calls === 1 ? 403 : 200);
        });
        Route::middleware('auth:api')->post('api/zz/idem-upload', function () {
            self::$calls++;

            return response()->json(['n' => self::$calls]);
        });
    }

    public function testSameKeyAndBodyReplaysTheStoredResponse(): void
    {
        $user = $this->makeAdmin();
        $h = ['Idempotency-Key' => 'abc-1'];
        $first = $this->actingAs($user, 'api')->postJson('/api/zz/idem', ['a' => 1], $h)->assertCreated();
        $second = $this->actingAs($user, 'api')->postJson('/api/zz/idem', ['a' => 1], $h)->assertCreated();

        $this->assertSame(1, self::$calls);
        $this->assertSame($first->json(), $second->json());
        $second->assertHeader('Idempotent-Replayed', 'true')->assertHeader('Location', '/api/zz/idem/1');
        $this->assertNull($first->headers->get('Idempotent-Replayed'));
    }

    public function testSameKeyWithAnotherBodyIsAMismatch(): void
    {
        $user = $this->makeAdmin();
        $h = ['Idempotency-Key' => 'abc-2'];
        $this->actingAs($user, 'api')->postJson('/api/zz/idem', ['a' => 1], $h)->assertCreated();
        $this->actingAs($user, 'api')->postJson('/api/zz/idem', ['a' => 2], $h)->assertStatus(422)->assertJsonPath('error', 'idempotency_mismatch');
        $this->assertSame(1, self::$calls);
    }

    public function testKeysAreScopedPerUserAndRoute(): void
    {
        $a = $this->makeAdmin();
        $b = $this->makeAdmin();
        $h = ['Idempotency-Key' => 'shared'];
        $this->actingAs($a, 'api')->postJson('/api/zz/idem', [], $h)->assertCreated();
        $this->actingAs($b, 'api')->postJson('/api/zz/idem', [], $h)->assertCreated();
        $this->actingAs($a, 'api')->postJson('/api/zz/idem-upload', [], $h)->assertOk();
        $this->assertSame(3, self::$calls);
    }

    public function testWithoutAKeyEveryRequestRuns(): void
    {
        $user = $this->makeAdmin();
        $this->actingAs($user, 'api')->postJson('/api/zz/idem', [])->assertCreated();
        $this->actingAs($user, 'api')->postJson('/api/zz/idem', [])->assertCreated();
        $this->assertSame(2, self::$calls);
    }

    public function testAConcurrentDuplicateGets409(): void
    {
        $user = $this->makeAdmin();
        $base = 'idempotency:' . hash('sha256', implode('|', ['localhost', $user->getAuthIdentifier(), 'POST', 'api/zz/idem', 'busy']));
        $lock = Cache::lock($base . ':lock', 30);
        $this->assertTrue($lock->get());
        $this->actingAs($user, 'api')->postJson('/api/zz/idem', [], ['Idempotency-Key' => 'busy'])
            ->assertStatus(409)->assertJsonPath('error', 'idempotency_in_progress');
        $this->assertSame(0, self::$calls);
        $lock->release();
        $this->actingAs($user, 'api')->postJson('/api/zz/idem', [], ['Idempotency-Key' => 'busy'])->assertCreated();
    }

    public function testServerErrorsAndForbiddenAnswersAreNotStored(): void
    {
        $user = $this->makeAdmin();
        $h = ['Idempotency-Key' => 'retry-me'];
        $this->actingAs($user, 'api')->postJson('/api/zz/idem-fail', [], $h)->assertStatus(500);
        $this->actingAs($user, 'api')->postJson('/api/zz/idem-fail', [], $h)->assertOk();
        self::$calls = 0;
        $h = ['Idempotency-Key' => 'retry-403'];
        $this->actingAs($user, 'api')->postJson('/api/zz/idem-forbidden', [], $h)->assertStatus(403);
        $this->actingAs($user, 'api')->postJson('/api/zz/idem-forbidden', [], $h)->assertOk();
    }

    public function testKeyLengthIsLimitedAndGuestsAreNotAffected(): void
    {
        $user = $this->makeAdmin();
        $this->actingAs($user, 'api')->postJson('/api/zz/idem', [], ['Idempotency-Key' => str_repeat('k', 256)])->assertStatus(422)->assertJsonPath('error', 'idempotency_key_invalid');
        $this->actingAs($user, 'api')->postJson('/api/zz/idem', [], ['Idempotency-Key' => str_repeat('k', 255)])->assertCreated();
    }

    public function testUploadsAreComparedByContent(): void
    {
        $user = $this->makeAdmin();
        $h = ['Idempotency-Key' => 'up-1'];
        $one = UploadedFile::fake()->createWithContent('a.txt', 'hello');
        $same = UploadedFile::fake()->createWithContent('a.txt', 'hello');
        $other = UploadedFile::fake()->createWithContent('a.txt', 'world');
        $this->actingAs($user, 'api')->post('/api/zz/idem-upload', ['file' => $one], $h)->assertOk();
        $this->actingAs($user, 'api')->post('/api/zz/idem-upload', ['file' => $same], $h)->assertOk()->assertHeader('Idempotent-Replayed', 'true');
        $this->actingAs($user, 'api')->post('/api/zz/idem-upload', ['file' => $other], $h)->assertStatus(422);
        $this->assertSame(1, self::$calls);
        $this->assertSame(86400, Idempotency::TTL_SECONDS);
    }
}
