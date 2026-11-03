<?php

namespace Ulams\Commerce\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\Commerce\Support\OrderItemSnapshot;
use Ulams\Commerce\Support\OrderSnapshot;

#[Test]
class OrderSnapshotMicroTest extends TestCase
{
    public function testOrderSnapshotConstruction()
    {
        $order = new OrderSnapshot(
            orderId: '12345',
            channel: 'web',
            userRef: 1001,
            state: OrderSnapshot::STATE_CANCELLED,
            paymentState: OrderSnapshot::PAYMENT_PAID,
            currency: 'USD',
            totalMinor: 1000,
            items: [],
        );

        $this->assertEquals('12345', $order->orderId);
        $this->assertEquals('web', $order->channel);
        $this->assertEquals(1001, $order->userRef);
        $this->assertEquals(OrderSnapshot::STATE_CANCELLED, $order->state);
        $this->assertEquals(OrderSnapshot::PAYMENT_PAID, $order->paymentState);
        $this->assertEquals('USD', $order->currency);
        $this->assertEquals(1000, $order->totalMinor);
        $this->assertEquals([], $order->items);
    }

    public function testOrderSnapshotWithOptionalFields()
    {
        $order = new OrderSnapshot(
            orderId: '67890',
            channel: 'mobile',
            userRef: null,
            state: 'pending',
            paymentState: OrderSnapshot::PAYMENT_PARTIALLY_REFUNDED,
            currency: 'EUR',
            totalMinor: 2000,
            items: [],
            number: 'ORD-67890',
            token: 'tok_67890',
            taxMinor: 300,
        );

        $this->assertEquals('67890', $order->orderId);
        $this->assertEquals('mobile', $order->channel);
        $this->assertNull($order->userRef);
        $this->assertEquals('pending', $order->state);
        $this->assertEquals(OrderSnapshot::PAYMENT_PARTIALLY_REFUNDED, $order->paymentState);
        $this->assertEquals('EUR', $order->currency);
        $this->assertEquals(2000, $order->totalMinor);
        $this->assertEquals([], $order->items);
        $this->assertEquals('ORD-67890', $order->number);
        $this->assertEquals('tok_67890', $order->token);
        $this->assertEquals(300, $order->taxMinor);
    }

    public function testOrderSnapshotWithItems()
    {
        $item1 = new OrderItemSnapshot(id: 'i_prod_1', productRef: 'prod_1', quantity: 2);

        $item2 = new OrderItemSnapshot(id: 'i_prod_2', productRef: 'prod_2', quantity: 1);

        $order = new OrderSnapshot(
            orderId: '112233',
            channel: 'api',
            userRef: 2002,
            state: 'completed',
            paymentState: OrderSnapshot::PAYMENT_REFUNDED,
            currency: 'GBP',
            totalMinor: 2500,
            items: [$item1, $item2],
        );

        $this->assertEquals('112233', $order->orderId);
        $this->assertEquals('api', $order->channel);
        $this->assertEquals(2002, $order->userRef);
        $this->assertEquals('completed', $order->state);
        $this->assertEquals(OrderSnapshot::PAYMENT_REFUNDED, $order->paymentState);
        $this->assertEquals('GBP', $order->currency);
        $this->assertEquals(2500, $order->totalMinor);
        $this->assertCount(2, $order->items);
        $this->assertEquals('prod_1', $order->items[0]->productRef);
        $this->assertEquals(2, $order->items[0]->quantity);
        $this->assertEquals('prod_2', $order->items[1]->productRef);
        $this->assertEquals(1, $order->items[1]->quantity);
    }

    public function testOrderSnapshotWithNoItems()
    {
        $order = new OrderSnapshot(
            orderId: '445566',
            channel: 'store',
            userRef: 3003,
            state: 'shipped',
            paymentState: OrderSnapshot::PAYMENT_PAID,
            currency: 'JPY',
            totalMinor: 1500,
            items: [],
        );

        $this->assertEquals('445566', $order->orderId);
        $this->assertEquals('store', $order->channel);
        $this->assertEquals(3003, $order->userRef);
        $this->assertEquals('shipped', $order->state);
        $this->assertEquals(OrderSnapshot::PAYMENT_PAID, $order->paymentState);
        $this->assertEquals('JPY', $order->currency);
        $this->assertEquals(1500, $order->totalMinor);
        $this->assertEquals([], $order->items);
    }
}
