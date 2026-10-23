<?php

namespace Ulams\LivingCourse\Connectors;

/**
 * A verified, relevant webhook delivery: its id (for duplicate detection), the event name and, when the payload says it, the
 * change that arrived (head commit, subject, pull request; see `Git\PushChange`).
 */
final class WebhookDelivery
{
    /** @param array<string,mixed>|null $change */
    public function __construct(public readonly string $id, public readonly string $event = 'push', public readonly ?array $change = null)
    {
    }
}
