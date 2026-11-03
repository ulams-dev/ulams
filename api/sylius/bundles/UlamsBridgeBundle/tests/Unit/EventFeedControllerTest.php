<?php

declare(strict_types=1);

namespace Ulams\Bridge\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Ulams\Bridge\Controller\EventFeedController;
use Ulams\Bridge\Entity\OutboxEvent;
use Ulams\Bridge\Repository\OutboxEventRepository;

final class EventFeedControllerTest extends TestCase
{
    private function event(string $seq, string $id): OutboxEvent
    {
        $e = new OutboxEvent($id, 'acme', 'tok', (int) $seq, 'order.paid', ['id' => $id, 'sequence' => (int) $seq]);
        (new \ReflectionProperty($e, 'seq'))->setValue($e, $seq);

        return $e;
    }

    public function testItReturnsTheChannelsEventsAfterTheCursorAndTheNextCursor(): void
    {
        $repo = $this->createMock(OutboxEventRepository::class);
        $repo->expects(self::once())->method('feed')->with('acme', 7, 200)->willReturn([$this->event('8', 'A'), $this->event('11', 'B')]);
        $repo->method('backlog')->with('acme')->willReturn(['pending' => 2, 'oldest' => '2026-10-23 00:00:00']);

        $response = (new EventFeedController($repo))(Request::create('/x', 'GET', ['channel' => 'acme', 'after' => '7']));
        $json = json_decode((string) $response->getContent(), true);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['A', 'B'], array_column($json['events'], 'id'));
        self::assertSame(['11', 2], [$json['nextCursor'], $json['undelivered']]);
    }

    public function testAnEmptyPageKeepsTheCursor(): void
    {
        $repo = $this->createMock(OutboxEventRepository::class);
        $repo->method('feed')->willReturn([]);
        $repo->method('backlog')->willReturn(['pending' => 0, 'oldest' => null]);

        $json = json_decode((string) (new EventFeedController($repo))(Request::create('/x', 'GET', ['channel' => 'acme', 'after' => '7']))->getContent(), true);

        self::assertSame(['events' => [], 'nextCursor' => '7'], ['events' => $json['events'], 'nextCursor' => $json['nextCursor']]);
    }

    public function testTheChannelIsRequiredAndTheLimitIsCapped(): void
    {
        $repo = $this->createMock(OutboxEventRepository::class);
        $repo->expects(self::once())->method('feed')->with('acme', 0, 500)->willReturn([]);
        $repo->method('backlog')->willReturn(['pending' => 0, 'oldest' => null]);
        $controller = new EventFeedController($repo);

        self::assertSame(400, $controller(Request::create('/x', 'GET'))->getStatusCode());
        self::assertSame(200, $controller(Request::create('/x', 'GET', ['channel' => 'acme', 'limit' => '99999', 'after' => '-5']))->getStatusCode());
    }
}
