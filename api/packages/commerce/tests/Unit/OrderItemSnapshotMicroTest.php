<?php

namespace Ulams\Commerce\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\Commerce\Support\OrderItemSnapshot;

#[\PHPUnit\Framework\Attributes\TestDox("OrderItemSnapshot")]
final class OrderItemSnapshotMicroTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function testConstructionWithDefaults()
    {
        $orderItem = new OrderItemSnapshot(id: '1', productRef: 'product1');
        $this->assertEquals('1', $orderItem->id);
        $this->assertEquals('product1', $orderItem->productRef);
        $this->assertEquals(1, $orderItem->quantity);
        $this->assertFalse($orderItem->refunded);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testConstructionWithCustomQuantity()
    {
        $orderItem = new OrderItemSnapshot(id: '2', productRef: 'product2', quantity: 5);
        $this->assertEquals(5, $orderItem->quantity);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testConstructionWithRefunded()
    {
        $orderItem = new OrderItemSnapshot(id: '3', productRef: 'product3', refunded: true);
        $this->assertTrue($orderItem->refunded);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testConstructionWithAllProperties()
    {
        $orderItem = new OrderItemSnapshot(id: '4', productRef: 'product4', quantity: 3, refunded: true);
        $this->assertEquals('4', $orderItem->id);
        $this->assertEquals('product4', $orderItem->productRef);
        $this->assertEquals(3, $orderItem->quantity);
        $this->assertTrue($orderItem->refunded);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testImmutabilityOfId()
    {
        $orderItem = new OrderItemSnapshot(id: '5', productRef: 'product5');
        $this->expectException(\Error::class);
        $orderItem->id = '6';
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testImmutabilityOfProductRef()
    {
        $orderItem = new OrderItemSnapshot(id: '7', productRef: 'product7');
        $this->expectException(\Error::class);
        $orderItem->productRef = 'product8';
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testImmutabilityOfQuantity()
    {
        $orderItem = new OrderItemSnapshot(id: '8', productRef: 'product8', quantity: 2);
        $this->expectException(\Error::class);
        $orderItem->quantity = 3;
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testImmutabilityOfRefunded()
    {
        $orderItem = new OrderItemSnapshot(id: '9', productRef: 'product9', refunded: false);
        $this->expectException(\Error::class);
        $orderItem->refunded = true;
    }
}
