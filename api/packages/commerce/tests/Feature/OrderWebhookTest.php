<?php

namespace Ulams\Commerce\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Ulams\Commerce\Jobs\ApplyOrderEvent;
use Ulams\Commerce\Tests\Support\OrderFlowTestCase;
use Ulams\Entitlements\Models\Entitlement;

/** Failure modes of the inbound order-event endpoint (ADR 0130, sections 5.2 and 5.3). */
class OrderWebhookTest extends OrderFlowTestCase
{
    private function paidEvent(): array
    {
        $user = $this->user();
        $this->course();
        $this->sylius->placeOrder('77', $user->getKey(), ['991' => 'variant-course-1']);

        return $this->sylius->pay('77');
    }

    private function nothingStored(): void
    {
        $this->assertSame(0, DB::table('commerce_order_events')->count());
        $this->assertSame(0, Entitlement::query()->count());
    }

    public function testAValidSignedEventIsStoredAndAnswered202(): void
    {
        Queue::fake();
        $event = $this->paidEvent();

        $this->deliver($event)->assertStatus(202)->assertJsonPath('data.queued', true);

        $this->assertDatabaseHas('commerce_order_events', ['provider' => 'fake', 'event_id' => $event['id'], 'order_id' => '77', 'sequence' => 1, 'source' => 'webhook']);
        Queue::assertPushed(ApplyOrderEvent::class, 1);
    }

    public function testABadSignatureIsRefusedWithoutAnyStateChange(): void
    {
        $this->deliver($this->paidEvent(), 'whsec_someone_else')->assertStatus(400);
        $this->nothingStored();
    }

    public function testAMissingSignatureIsRefused(): void
    {
        $this->deliver($this->paidEvent(), null)->assertStatus(400);
        $this->nothingStored();
    }

    public function testATamperedBodyIsRefused(): void
    {
        $event = $this->paidEvent();
        $signature = \Ulams\Webhooks\Support\WebhookSignature::sign(self::SECRET, json_encode($event));
        $event['order_id'] = '78';

        $this->deliverRaw(json_encode($event), $signature, $event['id'])->assertStatus(400);
        $this->nothingStored();
    }

    public function testAReplayedRequestOutsideTheTimestampToleranceIsRefused(): void
    {
        $event = $this->paidEvent();

        $this->deliver($event, self::SECRET, time() - 301)->assertStatus(400);
        $this->deliver($event, self::SECRET, time() + 301)->assertStatus(400);
        $this->nothingStored();

        $this->deliver($event, self::SECRET, time() - 200)->assertStatus(202);
    }

    public function testTheSecretBeingRotatedOutIsStillAccepted(): void
    {
        config(['commerce.webhook.secret' => 'whsec_new', 'commerce.webhook.previous_secret' => self::SECRET]);
        $event = $this->paidEvent();

        $this->deliver($event, self::SECRET)->assertStatus(202);
        $this->assertDatabaseCount('commerce_order_events', 1);
        $this->deliver($event, 'whsec_new')->assertOk()->assertJsonPath('data.duplicate', true);
        $this->deliver($this->sylius->feed[0], 'whsec_other')->assertStatus(400);
    }

    public function testADuplicateDeliveryAnswers200AndDoesNothing(): void
    {
        $event = $this->paidEvent();

        $this->deliver($event)->assertStatus(202);
        $this->deliver($event)->assertOk()->assertJsonPath('data.duplicate', true);
        $this->deliver($event)->assertOk()->assertJsonPath('data.duplicate', true);

        $this->assertDatabaseCount('commerce_order_events', 1);
        $this->assertSame(1, Entitlement::query()->count());
        $this->assertSame(1, $this->sylius->fetches, 'duplicates cost no Admin API call');
    }

    public function testTheEventIdHeaderMustEqualThePayloadId(): void
    {
        $this->deliver($this->paidEvent(), headers: ['Ulams-Event-Id' => 'something-else'])->assertStatus(400);
        $this->nothingStored();
    }

    public function testAnEventForAnotherChannelIsRefusedEvenWithAValidSignature(): void
    {
        $event = $this->paidEvent();
        $event['channel_code'] = 'globex';

        $this->deliver($event)->assertStatus(400);
        $this->nothingStored();
    }

    public function testMalformedEventsAreRefused(): void
    {
        $event = $this->paidEvent();
        foreach ([
            ['type' => 'order.exploded'], ['sequence' => 0], ['sequence' => '1'], ['version' => 'v2'], ['id' => ''], ['order_id' => str_repeat('x', 65)],
        ] as $override) {
            $this->deliver($override + $event)->assertStatus(400);
        }
        $this->deliverRaw('not json', \Ulams\Webhooks\Support\WebhookSignature::sign(self::SECRET, 'not json'), 'x')->assertStatus(400);
        $this->deliverRaw('[1,2]', \Ulams\Webhooks\Support\WebhookSignature::sign(self::SECRET, '[1,2]'), 'x')->assertStatus(400);
        $this->nothingStored();
    }

    public function testAnOversizedBodyIsRefused(): void
    {
        $event = $this->paidEvent() + ['padding' => str_repeat('a', 262144)];

        $this->deliver($event)->assertStatus(413);
        $this->nothingStored();
    }

    public function testUnknownOrUnconfiguredEndpointsAnswer404(): void
    {
        $event = $this->paidEvent();

        $this->deliver($event, provider: 'nope')->assertNotFound();
        $this->deliver($event, provider: 'wellms')->assertNotFound(); // the built-in cart reports no orders
        config(['commerce.webhook.secret' => null]);
        $this->deliver($event)->assertNotFound();
        config(['commerce.webhook.secret' => self::SECRET, 'commerce.channel' => null]);
        $this->deliver($event)->assertNotFound();
        $this->nothingStored();
    }

    public function testTheEndpointNeedsNoSessionOrToken(): void
    {
        $this->deliver($this->paidEvent())->assertStatus(202); // no Authorization header anywhere in this suite
    }
}
