<?php

namespace Ulams\Commerce\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\Commerce\Support\OrderEvent;
use Ulams\Commerce\Support\ProductRef;

class OrderEventMicroTest extends TestCase
{
    #[Test]
    public function testOrderEventConstruction()
    {
        $orderEvent = new OrderEvent(
            'provider1',
            OrderEvent::PAID,
            'idempotency123',
            'order456',
            new ProductRef('stripe', 'product789'),
            'buyer@example.com'
        );

        $this->assertInstanceOf(OrderEvent::class, $orderEvent);
    }

    #[Test]
    public function testOrderEventProperties()
    {
        $orderEvent = new OrderEvent(
            'provider1',
            OrderEvent::PAID,
            'idempotency123',
            'order456',
            new ProductRef('stripe', 'product789'),
            'buyer@example.com'
        );

        $this->assertEquals('provider1', $orderEvent->provider);
        $this->assertEquals(OrderEvent::PAID, $orderEvent->type);
        $this->assertEquals('idempotency123', $orderEvent->idempotencyKey);
        $this->assertEquals('order456', $orderEvent->orderId);
        $this->assertEquals('product789', $orderEvent->product->externalId);
        $this->assertEquals('stripe', $orderEvent->product->provider);
        $this->assertEquals('buyer@example.com', $orderEvent->buyerEmail);
    }

    #[Test]
    public function testOrderEventWithRefundedType()
    {
        $orderEvent = new OrderEvent(
            'provider2',
            OrderEvent::REFUNDED,
            'idempotency456',
            'order789',
            new ProductRef('product123', 5),
            'buyer2@example.com'
        );

        $this->assertEquals(OrderEvent::REFUNDED, $orderEvent->type);
    }

    #[Test]
    public function testOrderEventWithCancelledType()
    {
        $orderEvent = new OrderEvent(
            'provider3',
            OrderEvent::CANCELLED,
            'idempotency789',
            'order123',
            new ProductRef('product456', 3),
            'buyer3@example.com'
        );

        $this->assertEquals(OrderEvent::CANCELLED, $orderEvent->type);
    }

    #[Test]
    public function testOrderEventWithDifferentProvider()
    {
        $orderEvent = new OrderEvent(
            'provider4',
            OrderEvent::PAID,
            'idempotency101',
            'order202',
            new ProductRef('product303', 8),
            'buyer4@example.com'
        );

        $this->assertEquals('provider4', $orderEvent->provider);
    }

    #[Test]
    public function testOrderEventWithDifferentBuyerEmail()
    {
        $orderEvent = new OrderEvent(
            'provider5',
            OrderEvent::PAID,
            'idempotency202',
            'order303',
            new ProductRef('product404', 12),
            'buyer5@example.com'
        );

        $this->assertEquals('buyer5@example.com', $orderEvent->buyerEmail);
    }
}
