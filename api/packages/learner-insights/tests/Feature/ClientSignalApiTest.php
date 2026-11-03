<?php

namespace Ulams\LearnerInsights\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Passport\Bridge\AccessTokenRepository;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResourceServer;
use Ulams\LearnerInsights\Models\Signal;
use Ulams\LearnerInsights\Tests\TestCase;

class ClientSignalApiTest extends TestCase
{
    private function body(int $topicId, array $extra = []): array
    {
        return array_merge(['type' => 'media.replayed', 'topicId' => $topicId, 'value' => 12, 'media' => 'video', 'ref' => (string) Str::uuid()], $extra);
    }

    public function testAnEnrolledLearnerRecordsAReplay(): void
    {
        [$course, $topic] = $this->courseWithTopic();
        $user = $this->learner($course);
        $body = $this->body($topic->getKey());

        $this->actingAs($user, 'api')->postJson('/api/learner-insights/signals', $body)->assertStatus(202);
        $this->actingAs($user, 'api')->postJson('/api/learner-insights/signals', $body)->assertStatus(202);

        $signal = Signal::query()->firstOrFail();
        $this->assertSame(1, Signal::query()->count(), 'the client ref drops duplicates');
        $this->assertSame('media.replayed', $signal->type);
        $this->assertSame('client', $signal->source);
        $this->assertSame(12.0, $signal->value);
        $this->assertSame(['media' => 'video'], $signal->data);
        $this->assertSame($course->getKey(), $signal->course_id);
    }

    public function testOnlyMediaTypesAreAccepted(): void
    {
        [$course, $topic] = $this->courseWithTopic();
        $user = $this->learner($course);

        $this->actingAs($user, 'api')->postJson('/api/learner-insights/signals', $this->body($topic->getKey(), ['type' => 'quiz.answer']))->assertStatus(422);
        $this->actingAs($user, 'api')->postJson('/api/learner-insights/signals', $this->body($topic->getKey(), ['ref' => 'not-a-uuid']))->assertStatus(422);
        $this->assertSame(0, Signal::query()->count());
    }

    public function testALearnerWhoCannotAttendTheCourseIsRefused(): void
    {
        [, $topic] = $this->courseWithTopic();
        $stranger = $this->student();

        $this->actingAs($stranger, 'api')->postJson('/api/learner-insights/signals', $this->body($topic->getKey()))->assertForbidden();
        $this->assertSame(0, Signal::query()->count());
    }

    public function testUnknownTopicAndAnonymousCallers(): void
    {
        [$course] = $this->courseWithTopic();
        $user = $this->learner($course);

        $this->actingAs($user, 'api')->postJson('/api/learner-insights/signals', $this->body(999999))->assertNotFound();
        Auth::forgetGuards();
        $this->postJson('/api/learner-insights/signals', $this->body(1))->assertUnauthorized();
    }

    public function testNothingIsStoredWhileTheModuleIsOff(): void
    {
        config(['learner_insights.enabled' => false]);
        [$course, $topic] = $this->courseWithTopic();
        $user = $this->learner($course);

        $this->actingAs($user, 'api')->postJson('/api/learner-insights/signals', $this->body($topic->getKey()))->assertStatus(202);
        $this->assertSame(0, Signal::query()->count());
    }

    /** Tenants have their own Passport key pair (ADR 0007): a token of tenant A is useless on tenant B. */
    public function testATokenOfAnotherTenantIsRejected(): void
    {
        [$course, $topic] = $this->courseWithTopic();
        $user = $this->learner($course);
        $tokenA = $user->createToken('test')->accessToken;

        $this->postSignal($tokenA, $topic->getKey())->assertStatus(202);

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        config(['passport.private_key' => $private, 'passport.public_key' => openssl_pkey_get_details($key)['key']]);
        foreach ([AuthorizationServer::class, ResourceServer::class, AccessTokenRepository::class] as $service) {
            $this->app->forgetInstance($service);
        }
        Auth::forgetGuards();

        $this->postSignal($tokenA, $topic->getKey())->assertUnauthorized();
        $this->assertSame(1, Signal::query()->count());
    }

    private function postSignal(string $token, int $topicId)
    {
        $response = $this->postJson('/api/learner-insights/signals', $this->body($topicId), ['Authorization' => 'Bearer ' . $token]);
        Auth::forgetGuards();

        return $response;
    }
}
