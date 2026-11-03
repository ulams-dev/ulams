<?php

namespace Ulams\Commerce\Contracts;

use Ulams\Commerce\Support\OrderEventPage;

/**
 * Optional capability: a feed of the provider's order events in order, read by `ulams:commerce:reconcile` to
 * repair webhooks that never arrived. `$cursor` is opaque to the LMS (null = from the start).
 */
interface ReconcilesOrders
{
    public function orderEventsAfter(?string $cursor, int $limit): OrderEventPage;
}
