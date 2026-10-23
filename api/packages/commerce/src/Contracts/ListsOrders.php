<?php

namespace Ulams\Commerce\Contracts;

use Carbon\CarbonInterface;
use Ulams\Commerce\Support\OrderSnapshot;

/**
 * Optional capability: the channel's orders whose payment state changed since a date, for the nightly full sweep
 * (`ulams:commerce:reconcile --full`). The sweep does not depend on the event feed or on webhooks, so it also repairs
 * orders whose events were never recorded.
 */
interface ListsOrders
{
    /** @return iterable<OrderSnapshot> paid, partially refunded, refunded and cancelled orders of this tenant channel */
    public function ordersChangedSince(CarbonInterface $since): iterable;
}
