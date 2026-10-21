<?php

namespace Ulams\Commerce\Support;

use InvalidArgumentException;

/**
 * The only part of a provider event the LMS reads: who, which order, what kind, in which order. Schema `v1`.
 * Everything else in a payload is ignored, because grants follow the re-fetched order (ADR 0130).
 */
final class OrderEventEnvelope
{
    public const VERSION = 'v1';
    public const TYPES = ['order.paid', 'order.refunded', 'order.partially_refunded', 'order.cancelled'];

    public function __construct(
        public readonly string $eventId,
        public readonly string $type,
        public readonly string $channel,
        public readonly string $orderId,
        public readonly int $sequence,
    ) {
    }

    /** @throws InvalidArgumentException when the payload does not match schema v1 */
    public static function fromPayload(array $payload): self
    {
        foreach (['id', 'type', 'channel_code', 'order_id'] as $field) {
            if (!isset($payload[$field]) || !is_string($payload[$field]) || $payload[$field] === '' || strlen($payload[$field]) > 64) {
                throw new InvalidArgumentException("Field \"{$field}\" must be a short, non-empty string.");
            }
        }
        if (($payload['version'] ?? null) !== self::VERSION) {
            throw new InvalidArgumentException('Unsupported schema version.');
        }
        if (!in_array($payload['type'], self::TYPES, true)) {
            throw new InvalidArgumentException('Unknown event type.');
        }
        if (!isset($payload['sequence']) || !is_int($payload['sequence']) || $payload['sequence'] < 1) {
            throw new InvalidArgumentException('Field "sequence" must be a positive integer.');
        }

        return new self($payload['id'], $payload['type'], $payload['channel_code'], $payload['order_id'], $payload['sequence']);
    }
}
