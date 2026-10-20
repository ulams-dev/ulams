<?php

namespace Ulams\Webhooks\Tests\Feature;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Queue;
use Ulams\Core\Http\SafeHttp;
use Ulams\Webhooks\Jobs\DeliverWebhookJob;
use Ulams\Webhooks\Models\WebhookAttempt;
use Ulams\Webhooks\Models\WebhookDelivery;
use Ulams\Webhooks\Services\DeliveryRunner;
use Ulams\Webhooks\Services\EndpointService;
use Ulams\Webhooks\Services\WebhookDispatcher;
use Ulams\Webhooks\Support\WebhookSignature;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Webhooks\Tests\TestCase;

class DeliveryTest extends TestCase
{
    use CreatesUsers;

    private function dispatch(string $type = 'course.completed', array $object = ['course' => ['id' => 7, 'title' => 'Coffee']]): int
    {
        return app(WebhookDispatcher::class)->dispatch($type, $object);
    }

    public function testADomainEventIsSignedAndDeliveredToSubscribedEndpointsOnly(): void
    {
        [$subscribed, $secret] = $this->endpoint(['events' => ['course.completed']]);
        [$other] = $this->endpoint(['url' => 'https://other.example.com/hook', 'events' => ['enrolment.created']]);
        $this->receiver([200]);

        $this->assertSame(1, $this->dispatch());

        $this->assertCount(1, $this->received);
        $request = $this->received[0];
        $body = (string) $request->getBody();
        $this->assertSame('https://hooks.example.com/ulams', (string) $request->getUri());
        $this->assertTrue(WebhookSignature::verify($request->getHeaderLine('Ulams-Signature'), $body, $secret));
        $this->assertSame('course.completed', $request->getHeaderLine('Ulams-Event-Type'));
        $this->assertSame('2026-10-20', $request->getHeaderLine('Ulams-Api-Version'));
        $this->assertSame('1', $request->getHeaderLine('Ulams-Delivery-Attempt'));
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));

        $event = json_decode($body, true);
        $this->assertSame('event', $event['object']);
        $this->assertSame('course.completed', $event['type']);
        $this->assertSame('2026-10-20', $event['api_version']);
        $this->assertSame($event['id'], $request->getHeaderLine('Ulams-Event-Id'));
        $this->assertSame(['course' => ['id' => 7, 'title' => 'Coffee']], $event['data']['object']);

        $delivery = WebhookDelivery::query()->where('endpoint_id', $subscribed->id)->firstOrFail();
        $this->assertSame(WebhookDelivery::SUCCEEDED, $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertSame(200, $delivery->last_status_code);
        $this->assertSame(0, WebhookDelivery::query()->where('endpoint_id', $other->id)->count());
        $this->assertNotNull($subscribed->refresh()->last_delivery_at);
    }

    public function testADisabledEndpointReceivesNothing(): void
    {
        [$endpoint] = $this->endpoint();
        $endpoint->forceFill(['enabled' => false])->save();

        $this->assertSame(0, $this->dispatch());
        $this->assertSame(0, WebhookDelivery::query()->count());
    }

    public function testAFailedAttemptIsRetriedWithExponentialBackoffAndEveryAttemptIsLogged(): void
    {
        Queue::fake();
        config(['queue.default' => 'database', 'ulams_webhooks.backoff_seconds' => [60, 300, 1800], 'ulams_webhooks.max_attempts' => 4]);
        [$endpoint] = $this->endpoint();
        $this->receiver([500, 503, new ConnectException('Connection refused', new Request('POST', 'https://hooks.example.com/ulams')), 200]);
        $runner = app(DeliveryRunner::class);

        $this->dispatch();
        $delivery = WebhookDelivery::query()->firstOrFail();
        $waits = [];
        for ($i = 0; $i < 3; $i++) {
            $runner->attempt($delivery->refresh());
            $delivery->refresh();
            $this->assertSame(WebhookDelivery::RETRYING, $delivery->status);
            $waits[] = (int) round(now()->diffInSeconds($delivery->next_attempt_at, false));
        }
        $this->assertEqualsWithDelta([60, 300, 1800], $waits, 2);
        Queue::assertPushed(DeliverWebhookJob::class, 4); // the first dispatch and one per retry

        $runner->attempt($delivery->refresh());
        $delivery->refresh();
        $this->assertSame(WebhookDelivery::SUCCEEDED, $delivery->status);
        $this->assertSame(4, $delivery->attempts);
        $this->assertSame([500, 503, null, 200], WebhookAttempt::query()->orderBy('number')->pluck('status_code')->all());
        $this->assertStringContainsString('Connection refused', (string) WebhookAttempt::query()->where('number', 3)->value('error'));
        $this->assertSame(0, $endpoint->refresh()->consecutive_failures);
    }

    public function testADeliveryFailsAfterTheLastAttemptAndAnEndpointThatKeepsFailingIsDisabled(): void
    {
        config(['ulams_webhooks.max_attempts' => 2, 'ulams_webhooks.disable_after_failures' => 2]);
        [$endpoint] = $this->endpoint();
        $this->receiver([500, 500, 500, 500]);
        $runner = app(DeliveryRunner::class);

        foreach ([1, 2] as $round) {
            $this->dispatch();
            $delivery = WebhookDelivery::query()->latest('id')->firstOrFail();
            $runner->attempt($delivery->refresh());
            $this->assertSame(WebhookDelivery::FAILED, $delivery->refresh()->status, "round {$round}");
        }

        $endpoint->refresh();
        $this->assertFalse($endpoint->enabled);
        $this->assertSame(2, $endpoint->consecutive_failures);
        $this->assertStringContainsString('2 failed deliveries', (string) $endpoint->disabled_reason);
        $this->assertSame(0, $this->dispatch(), 'a disabled endpoint gets no new events');
    }

    public function testRedirectsAreNotFollowed(): void
    {
        [$endpoint] = $this->endpoint();
        $this->receiver([new \GuzzleHttp\Psr7\Response(302, ['Location' => 'https://169.254.169.254/latest/meta-data/'])]);

        $this->dispatch();

        $delivery = WebhookDelivery::query()->where('endpoint_id', $endpoint->id)->firstOrFail();
        $this->assertSame(WebhookDelivery::RETRYING, $delivery->status);
        $this->assertSame(302, $delivery->last_status_code);
        $this->assertCount(1, $this->received);
    }

    public function testAHostThatStartsResolvingToAPrivateAddressIsNotCalled(): void
    {
        [$endpoint] = $this->endpoint();
        $this->receiver([200]);
        // the DNS answer changed after the endpoint was registered (rebinding)
        SafeHttp::useResolver(fn (string $host) => ['169.254.169.254']);

        $this->dispatch();

        $delivery = WebhookDelivery::query()->where('endpoint_id', $endpoint->id)->firstOrFail();
        $this->assertSame([], $this->received, 'the request must not leave the process');
        $this->assertSame(WebhookDelivery::RETRYING, $delivery->status);
        $this->assertStringContainsString('private addresses', (string) WebhookAttempt::query()->where('delivery_id', $delivery->id)->value('error'));
    }

    public function testATestSendRunsOnceWithoutRetriesAndReportsTheAnswer(): void
    {
        [$endpoint, $secret] = $this->endpoint(['events' => ['course.completed']]);
        $this->receiver([500]);

        $delivery = app(EndpointService::class)->sendTest($endpoint);

        $this->assertTrue($delivery->is_test);
        $this->assertSame(WebhookDelivery::FAILED, $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertSame('webhook.test', $delivery->event_type);
        $this->assertTrue(WebhookSignature::verify($this->received[0]->getHeaderLine('Ulams-Signature'), (string) $this->received[0]->getBody(), $secret));
        $this->assertSame(0, $endpoint->refresh()->consecutive_failures, 'a test does not count against the endpoint');
    }

    public function testAReplayIsANewDeliveryWithTheSameEventAndAFreshSignature(): void
    {
        [$endpoint, $secret] = $this->endpoint();
        $this->receiver([200, 200]);
        $this->dispatch();
        $original = WebhookDelivery::query()->firstOrFail();

        $copy = app(EndpointService::class)->replay($original);

        $this->assertNotSame($original->id, $copy->id);
        $this->assertSame($original->id, $copy->replay_of);
        $this->assertSame($original->event_id, $copy->event_id);
        $this->assertSame($original->payload, $copy->payload);
        $this->assertSame(WebhookDelivery::SUCCEEDED, $copy->status);
        $this->assertCount(2, $this->received);
        $this->assertSame((string) $this->received[0]->getBody(), (string) $this->received[1]->getBody());
        $this->assertTrue(WebhookSignature::verify($this->received[1]->getHeaderLine('Ulams-Signature'), (string) $this->received[1]->getBody(), $secret));
    }

    public function testAnAttemptThatIsAlreadyClaimedIsNotRepeated(): void
    {
        [$endpoint] = $this->endpoint();
        $this->receiver([200, 200]);
        $this->dispatch();
        $delivery = WebhookDelivery::query()->firstOrFail();

        // two workers picked the same job: the second sees a finished delivery and does nothing
        app(DeliveryRunner::class)->attempt($delivery);
        app(DeliveryRunner::class)->attempt($delivery);

        $this->assertCount(1, $this->received);
        $this->assertSame(1, WebhookAttempt::query()->count());
    }

    public function testRetriesThatAreOverdueAreQueuedAgain(): void
    {
        Queue::fake();
        [$endpoint] = $this->endpoint();
        $due = WebhookDelivery::query()->create(['endpoint_id' => $endpoint->id, 'event_id' => 'evt_a', 'event_type' => 'course.completed', 'payload' => '{}', 'status' => WebhookDelivery::RETRYING, 'attempts' => 1, 'next_attempt_at' => now()->subMinutes(10)]);
        WebhookDelivery::query()->create(['endpoint_id' => $endpoint->id, 'event_id' => 'evt_b', 'event_type' => 'course.completed', 'payload' => '{}', 'status' => WebhookDelivery::RETRYING, 'attempts' => 1, 'next_attempt_at' => now()->addMinutes(10)]);

        $this->artisan('webhooks:retry-due --grace=0')->assertSuccessful();

        Queue::assertPushed(DeliverWebhookJob::class, 1);
        Queue::assertPushed(DeliverWebhookJob::class, fn (DeliverWebhookJob $job) => $job->deliveryId === $due->id);
    }

    public function testOldDeliveriesArePruned(): void
    {
        [$endpoint] = $this->endpoint();
        config(['ulams_webhooks.retention_days' => 30]);
        $old = WebhookDelivery::query()->create(['endpoint_id' => $endpoint->id, 'event_id' => 'evt_a', 'event_type' => 'x', 'payload' => '{}', 'status' => WebhookDelivery::SUCCEEDED]);
        $old->forceFill(['created_at' => now()->subDays(31)])->save();
        $pendingOld = WebhookDelivery::query()->create(['endpoint_id' => $endpoint->id, 'event_id' => 'evt_b', 'event_type' => 'x', 'payload' => '{}', 'status' => WebhookDelivery::RETRYING]);
        $pendingOld->forceFill(['created_at' => now()->subDays(31)])->save();
        $fresh = WebhookDelivery::query()->create(['endpoint_id' => $endpoint->id, 'event_id' => 'evt_c', 'event_type' => 'x', 'payload' => '{}', 'status' => WebhookDelivery::SUCCEEDED]);

        $this->artisan('webhooks:prune')->assertSuccessful();

        $this->assertEqualsCanonicalizing([$pendingOld->id, $fresh->id], WebhookDelivery::query()->pluck('id')->all());
    }
}
