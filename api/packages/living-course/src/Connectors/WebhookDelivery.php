<?php

namespace Ulams\LivingCourse\Connectors;

/** A verified, relevant webhook delivery: its id (for duplicate detection) and the event name. */
final class WebhookDelivery
{
    public function __construct(public readonly string $id, public readonly string $event = 'push')
    {
    }
}
