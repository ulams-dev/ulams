<?php

declare(strict_types=1);

namespace Ulams\Bridge\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\Bridge\Entity\OutboxEvent;

final class OutboxEventTest extends TestCase
{
    private function event(): OutboxEvent
    {
        return new OutboxEvent('01HZX0000000000000000000AA', 'acme', 'tok', 1, 'order.paid', ['id' => '01HZX0000000000000000000AA']);
    }

    public function testANewEventIsDueImmediatelyAndNotDelivered(): void
    {
        $e = $this->event();

        self::assertNull($e->getDeliveredAt());
        self::assertLessThanOrEqual(new \DateTimeImmutable(), $e->getNextAttemptAt());
        self::assertSame(0, $e->getAttempts());
    }

    public function testFailuresBackOffExponentiallyUpToAnHour(): void
    {
        $e = $this->event();
        $delays = [];
        for ($i = 0; $i < 12; $i++) {
            $before = time();
            $e->markFailed('boom');
            $delays[] = $e->getNextAttemptAt()->getTimestamp() - $before;
        }

        self::assertEqualsWithDelta(20, $delays[0], 2);
        self::assertEqualsWithDelta(40, $delays[1], 2);
        self::assertGreaterThan($delays[0], $delays[3]);
        self::assertEqualsWithDelta(3600, $delays[11], 2, 'capped at one hour');
        self::assertSame(12, $e->getAttempts());
        self::assertSame('boom', $e->getLastError());
    }

    public function testDeliveryClearsTheRetry(): void
    {
        $e = $this->event();
        $e->markFailed('boom');
        $e->markDelivered();

        self::assertNotNull($e->getDeliveredAt());
        self::assertNull($e->getNextAttemptAt());
        self::assertNull($e->getLastError());
    }

    public function testAnEventOlderThanTheRetryWindowIsNoLongerRetried(): void
    {
        $e = $this->event();
        $created = new \ReflectionProperty($e, 'createdAt');
        $created->setValue($e, new \DateTimeImmutable('-73 hours'));

        $e->markFailed('still down');

        self::assertNull($e->getNextAttemptAt(), 'it stays in the feed, where reconciliation reads it');
    }

    public function testALongErrorIsCut(): void
    {
        $e = $this->event();
        $e->markFailed(str_repeat('x', 5000));

        self::assertSame(500, strlen((string) $e->getLastError()));
    }
}
