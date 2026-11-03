<?php

declare(strict_types=1);

namespace Ulams\Bridge\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * One thing that happened to an order, written in the same database transaction as the state change (a transactional
 * outbox, ADR 0130): the event exists if and only if the transition committed. `seq` orders the pull feed, `sequence`
 * orders the events of one order. The payload is schema `v1`; the LMS reads only the envelope and re-reads the order.
 */
#[ORM\Entity(repositoryClass: \Ulams\Bridge\Repository\OutboxEventRepository::class)]
#[ORM\Table(name: 'ulams_outbox_event')]
#[ORM\Index(name: 'ulams_outbox_channel_seq', columns: ['channel_code', 'seq'])]
#[ORM\Index(name: 'ulams_outbox_pending', columns: ['delivered_at', 'next_attempt_at'])]
#[ORM\UniqueConstraint(name: 'ulams_outbox_order_sequence', columns: ['order_token', 'sequence'])]
class OutboxEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'bigint')]
    private ?string $seq = null;

    /** ULID: the event id the LMS stores once per provider. */
    #[ORM\Column(type: 'string', length: 26, unique: true)]
    private string $id;

    #[ORM\Column(name: 'channel_code', type: 'string', length: 64)]
    private string $channelCode;

    /** The order's token value: the key of `GET /api/v2/admin/orders/{token}`. */
    #[ORM\Column(name: 'order_token', type: 'string', length: 64)]
    private string $orderToken;

    #[ORM\Column(type: 'integer')]
    private int $sequence;

    #[ORM\Column(type: 'string', length: 32)]
    private string $type;

    /** @var array<string,mixed> */
    #[ORM\Column(type: 'json')]
    private array $payload;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'delivered_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deliveredAt = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $attempts = 0;

    #[ORM\Column(name: 'next_attempt_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $nextAttemptAt = null;

    #[ORM\Column(name: 'last_error', type: 'text', nullable: true)]
    private ?string $lastError = null;

    /** @param array<string,mixed> $payload */
    public function __construct(string $id, string $channelCode, string $orderToken, int $sequence, string $type, array $payload)
    {
        $this->id = $id;
        $this->channelCode = $channelCode;
        $this->orderToken = $orderToken;
        $this->sequence = $sequence;
        $this->type = $type;
        $this->payload = $payload;
        $this->createdAt = new \DateTimeImmutable();
        $this->nextAttemptAt = $this->createdAt;
    }

    public function getSeq(): ?string { return $this->seq; }
    public function getId(): string { return $this->id; }
    public function getChannelCode(): string { return $this->channelCode; }
    public function getOrderToken(): string { return $this->orderToken; }
    public function getSequence(): int { return $this->sequence; }
    public function getType(): string { return $this->type; }
    /** @return array<string,mixed> */
    public function getPayload(): array { return $this->payload; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getDeliveredAt(): ?\DateTimeImmutable { return $this->deliveredAt; }
    public function getAttempts(): int { return $this->attempts; }
    public function getNextAttemptAt(): ?\DateTimeImmutable { return $this->nextAttemptAt; }
    public function getLastError(): ?string { return $this->lastError; }

    public function markDelivered(): void
    {
        $this->deliveredAt = new \DateTimeImmutable();
        $this->nextAttemptAt = null;
        $this->lastError = null;
    }

    public const RETRY_WINDOW = '+72 hours';

    /** Exponential backoff (20 s, 40 s ... capped at one hour); 72 hours after creation the row stays in the feed but is no longer retried. */
    public function markFailed(string $error): void
    {
        ++$this->attempts;
        $this->lastError = mb_substr($error, 0, 500);
        $now = new \DateTimeImmutable();
        $this->nextAttemptAt = $now > $this->createdAt->modify(self::RETRY_WINDOW) ? null : $now->modify('+' . min(3600, 10 * (2 ** min($this->attempts, 9))) . ' seconds');
    }
}
