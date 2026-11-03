<?php

namespace Ulams\Commerce\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\Commerce\Support\OrderEventEnvelope;
use InvalidArgumentException;

/**
 * @covers \Ulams\Commerce\Support\OrderEventEnvelope
 */
class OrderEventEnvelopeMicroTest extends TestCase
{
    #[Test]
    public function testConstructionWithValidData()
    {
        $envelope = new OrderEventEnvelope(
            'event123',
            'order.paid',
            'channel1',
            'order456',
            1
        );

        $this->assertEquals('event123', $envelope->eventId);
        $this->assertEquals('order.paid', $envelope->type);
        $this->assertEquals('channel1', $envelope->channel);
        $this->assertEquals('order456', $envelope->orderId);
        $this->assertEquals(1, $envelope->sequence);
    }

    #[Test]
    public function testFromPayloadWithValidData()
    {
        $payload = [
            'id' => 'event123',
            'type' => 'order.paid',
            'channel_code' => 'channel1',
            'order_id' => 'order456',
            'sequence' => 1,
            'version' => 'v1',
        ];

        $envelope = OrderEventEnvelope::fromPayload($payload);

        $this->assertEquals('event123', $envelope->eventId);
        $this->assertEquals('order.paid', $envelope->type);
        $this->assertEquals('channel1', $envelope->channel);
        $this->assertEquals('order456', $envelope->orderId);
        $this->assertEquals(1, $envelope->sequence);
    }

    #[Test]
    public function testFromPayloadWithInvalidVersion()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported schema version.');

        $payload = [
            'id' => 'event123',
            'type' => 'order.paid',
            'channel_code' => 'channel1',
            'order_id' => 'order456',
            'sequence' => 1,
            'version' => 'v2',
        ];

        OrderEventEnvelope::fromPayload($payload);
    }

    #[Test]
    public function testFromPayloadWithInvalidType()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown event type.');

        $payload = [
            'id' => 'event123',
            'type' => 'order.unknown',
            'channel_code' => 'channel1',
            'order_id' => 'order456',
            'sequence' => 1,
            'version' => 'v1',
        ];

        OrderEventEnvelope::fromPayload($payload);
    }

    #[Test]
    public function testFromPayloadWithInvalidSequence()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field "sequence" must be a positive integer.');

        $payload = [
            'id' => 'event123',
            'type' => 'order.paid',
            'channel_code' => 'channel1',
            'order_id' => 'order456',
            'sequence' => -1,
            'version' => 'v1',
        ];

        OrderEventEnvelope::fromPayload($payload);
    }

    #[Test]
    public function testFromPayloadWithMissingField()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field "id" must be a short, non-empty string.');

        $payload = [
            'type' => 'order.paid',
            'channel_code' => 'channel1',
            'order_id' => 'order456',
            'sequence' => 1,
            'version' => 'v1',
        ];

        OrderEventEnvelope::fromPayload($payload);
    }

    #[Test]
    public function testFromPayloadWithEmptyField()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field "id" must be a short, non-empty string.');

        $payload = [
            'id' => '',
            'type' => 'order.paid',
            'channel_code' => 'channel1',
            'order_id' => 'order456',
            'sequence' => 1,
            'version' => 'v1',
        ];

        OrderEventEnvelope::fromPayload($payload);
    }

    #[Test]
    public function testFromPayloadWithTooLongField()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field "id" must be a short, non-empty string.');

        $payload = [
            'id' => str_repeat('a', 65),
            'type' => 'order.paid',
            'channel_code' => 'channel1',
            'order_id' => 'order456',
            'sequence' => 1,
            'version' => 'v1',
        ];

        OrderEventEnvelope::fromPayload($payload);
    }
}
