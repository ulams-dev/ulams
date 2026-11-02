<?php

namespace Ulams\Commerce\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\Commerce\Support\OrderEventPage;

class OrderEventPageMicroTest extends TestCase
{
    #[Test]
    public function testConstructionWithEventsAndCursor()
    {
        $events = [
            // Mock OrderEventEnvelope objects
            (object) ['id' => 1],
            (object) ['id' => 2],
        ];
        $nextCursor = 'cursor123';
        $orderEventPage = new OrderEventPage($events, $nextCursor);

        $this->assertEquals($events, $orderEventPage->events);
        $this->assertEquals($nextCursor, $orderEventPage->nextCursor);
    }

    #[Test]
    public function testConstructionWithEmptyEventsAndCursor()
    {
        $events = [];
        $nextCursor = null;
        $orderEventPage = new OrderEventPage($events, $nextCursor);

        $this->assertEquals($events, $orderEventPage->events);
        $this->assertEquals($nextCursor, $orderEventPage->nextCursor);
    }

    #[Test]
    public function testConstructionWithEventsAndNullCursor()
    {
        $events = [
            // Mock OrderEventEnvelope objects
            (object) ['id' => 1],
            (object) ['id' => 2],
        ];
        $nextCursor = null;
        $orderEventPage = new OrderEventPage($events, $nextCursor);

        $this->assertEquals($events, $orderEventPage->events);
        $this->assertEquals($nextCursor, $orderEventPage->nextCursor);
    }

    #[Test]
    public function testConstructionWithEmptyEventsAndStringCursor()
    {
        $events = [];
        $nextCursor = 'cursor456';
        $orderEventPage = new OrderEventPage($events, $nextCursor);

        $this->assertEquals($events, $orderEventPage->events);
        $this->assertEquals($nextCursor, $orderEventPage->nextCursor);
    }

    #[Test]
    public function testEventsPropertyIsReadOnly()
    {
        $orderEventPage = new OrderEventPage([], 'cursor789');
        $this->expectException(\Error::class);
        $orderEventPage->events = [];
    }

    #[Test]
    public function testNextCursorPropertyIsReadOnly()
    {
        $orderEventPage = new OrderEventPage([], 'cursor789');
        $this->expectException(\Error::class);
        $orderEventPage->nextCursor = 'newCursor';
    }
}
