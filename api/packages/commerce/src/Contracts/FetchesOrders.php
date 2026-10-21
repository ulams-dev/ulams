<?php

namespace Ulams\Commerce\Contracts;

use Ulams\Commerce\Support\OrderSnapshot;

/**
 * Optional capability of a provider that reports orders by webhook (ADR 0130, section 5.3). The LMS never acts
 * on a webhook payload: it reads the order back through this method and acts on the provider's current state,
 * so a leaked webhook secret cannot grant access the provider did not record. Throws when the provider is
 * unreachable; the job retries.
 */
interface FetchesOrders
{
    public function fetchOrder(string $orderId): OrderSnapshot;
}
