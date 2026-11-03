<?php

declare(strict_types=1);

namespace Ulams\Bridge\Tests\Unit;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;
use Ulams\Bridge\Entity\OutboxEvent;
use Ulams\Bridge\EventListener\RecordOrderEvents;
use Ulams\Bridge\Repository\OutboxEventRepository;

/** The recorder only persists rows (the caller's flush commits them with the transition); it never flushes itself. */
final class RecordOrderEventsTest extends TestCase
{
    /** @var list<OutboxEvent> */
    private array $persisted = [];
    private int $lastSequence = 0;
    private RecordOrderEvents $recorder;

    protected function setUp(): void
    {
        $uow = $this->createMock(UnitOfWork::class);
        $uow->method('getScheduledEntityInsertions')->willReturnCallback(fn () => $this->persisted);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($uow);
        $em->method('persist')->willReturnCallback(function (object $o): void {
            $this->persisted[] = $o;
        });
        $em->expects(self::never())->method('flush');
        $repo = $this->createMock(OutboxEventRepository::class);
        $repo->method('nextSequence')->willReturnCallback(fn () => $this->lastSequence + 1);
        $this->recorder = new RecordOrderEvents($em, $repo);
    }

    private function order(string $paymentState = 'paid', ?string $token = 'tok-1', string $channel = 'acme'): OrderInterface
    {
        $ch = $this->createMock(ChannelInterface::class);
        $ch->method('getCode')->willReturn($channel);
        $order = $this->createMock(OrderInterface::class);
        $order->method('getTokenValue')->willReturn($token);
        $order->method('getChannel')->willReturn($ch);
        $order->method('getNumber')->willReturn('000000042');
        $order->method('getPaymentState')->willReturn($paymentState);

        return $order;
    }

    private function completed(OrderInterface $order): CompletedEvent
    {
        return new CompletedEvent($order, new Marking(), new Transition('t', 'a', 'b'));
    }

    public function testPaidWritesAV1EnvelopeForTheOrdersChannel(): void
    {
        $this->recorder->paid($this->completed($this->order()));

        self::assertCount(1, $this->persisted);
        $e = $this->persisted[0];
        self::assertSame(['acme', 'tok-1', 1, 'order.paid'], [$e->getChannelCode(), $e->getOrderToken(), $e->getSequence(), $e->getType()]);
        $p = $e->getPayload();
        self::assertSame(['v1', $e->getId(), 'order.paid', 'acme', 'tok-1', 1], [$p['version'], $p['id'], $p['type'], $p['channel_code'], $p['order_id'], $p['sequence']]);
        self::assertSame(26, strlen($e->getId()), 'a ULID');
        self::assertArrayNotHasKey('items', $p, 'the LMS reads the order back; the payload stays an envelope');
    }

    public function testTwoTransitionsInOneRequestKeepTheirOrder(): void
    {
        $order = $this->order();
        $this->recorder->paid($this->completed($order));
        $this->recorder->refunded($this->completed($order));
        $this->recorder->partiallyRefunded($this->completed($order));

        self::assertSame([1, 2, 3], array_map(fn (OutboxEvent $e) => $e->getSequence(), $this->persisted));
        self::assertSame(['order.paid', 'order.refunded', 'order.partially_refunded'], array_map(fn (OutboxEvent $e) => $e->getType(), $this->persisted));
    }

    public function testTheSequenceContinuesAfterWhatIsAlreadyStored(): void
    {
        $this->lastSequence = 4;
        $this->recorder->refunded($this->completed($this->order('refunded')));

        self::assertSame(5, $this->persisted[0]->getSequence());
    }

    public function testACancellationOfAnUnpaidOrderIsNotAnEvent(): void
    {
        $this->recorder->cancelled($this->completed($this->order('awaiting_payment')));
        self::assertSame([], $this->persisted);

        $this->recorder->cancelled($this->completed($this->order('paid')));
        self::assertSame('order.cancelled', $this->persisted[0]->getType());
    }

    public function testAnOrderWithoutATokenOrChannelOrNotAnOrderIsIgnored(): void
    {
        $this->recorder->paid($this->completed($this->order(token: null)));
        $this->recorder->paid(new CompletedEvent(new \stdClass(), new Marking(), new Transition('t', 'a', 'b')));
        $noChannel = $this->createMock(OrderInterface::class);
        $noChannel->method('getTokenValue')->willReturn('t');
        $noChannel->method('getChannel')->willReturn(null);
        $this->recorder->paid($this->completed($noChannel));

        self::assertSame([], $this->persisted);
    }
}
