<?php

declare(strict_types=1);

namespace Ulams\Bridge\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Ulams\Bridge\Entity\OutboxEvent;
use Ulams\Bridge\Repository\OutboxEventRepository;

/**
 * Writes an outbox row for every payment transition of an order, on the Symfony Workflow events Sylius itself uses.
 * The row is only persisted here; the caller's flush commits it in the same transaction as the transition, so an event
 * exists if and only if the transition committed. The payload is deliberately small (schema v1): the LMS acts on the
 * order it reads back, not on this.
 */
final class RecordOrderEvents
{
    public const SCHEMA = 'v1';

    public function __construct(private readonly EntityManagerInterface $em, private readonly OutboxEventRepository $outbox)
    {
    }

    #[AsEventListener(event: 'workflow.sylius_order_payment.completed.pay')]
    public function paid(CompletedEvent $event): void
    {
        $this->record($event, 'order.paid');
    }

    #[AsEventListener(event: 'workflow.sylius_order_payment.completed.refund')]
    public function refunded(CompletedEvent $event): void
    {
        $this->record($event, 'order.refunded');
    }

    #[AsEventListener(event: 'workflow.sylius_order_payment.completed.partially_refund')]
    public function partiallyRefunded(CompletedEvent $event): void
    {
        $this->record($event, 'order.partially_refunded');
    }

    /** A cancellation only matters to the LMS when money had been paid (otherwise nothing was ever granted). */
    #[AsEventListener(event: 'workflow.sylius_order.completed.cancel')]
    public function cancelled(CompletedEvent $event): void
    {
        $order = $event->getSubject();
        if ($order instanceof OrderInterface && in_array($order->getPaymentState(), [OrderPaymentStates::STATE_PAID, OrderPaymentStates::STATE_PARTIALLY_REFUNDED, OrderPaymentStates::STATE_REFUNDED], true)) {
            $this->record($event, 'order.cancelled');
        }
    }

    private function record(CompletedEvent $event, string $type): void
    {
        $order = $event->getSubject();
        if (!$order instanceof OrderInterface || $order->getTokenValue() === null || $order->getChannel() === null) {
            return;
        }
        $token = (string) $order->getTokenValue();
        $channel = (string) $order->getChannel()->getCode();
        // a pending (not yet flushed) event of the same order counts too, so two transitions in one request keep their order
        $sequence = $this->outbox->nextSequence($token);
        foreach ($this->em->getUnitOfWork()->getScheduledEntityInsertions() as $pending) {
            if ($pending instanceof OutboxEvent && $pending->getOrderToken() === $token) {
                $sequence = max($sequence, $pending->getSequence() + 1);
            }
        }
        $id = (string) new Ulid();
        $this->em->persist(new OutboxEvent($id, $channel, $token, $sequence, $type, [
            'version' => self::SCHEMA, 'id' => $id, 'type' => $type, 'channel_code' => $channel, 'order_id' => $token,
            'sequence' => $sequence, 'number' => $order->getNumber(), 'occurred_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ]));
    }
}
