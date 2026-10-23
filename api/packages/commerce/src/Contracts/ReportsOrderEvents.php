<?php

namespace Ulams\Commerce\Contracts;

/**
 * Marker capability: the provider reports orders to the LMS by signed webhook (ADR 0130), so
 * `POST /api/commerce/webhooks/{provider}` accepts it. A provider without it (the Wellms cart, which grants access
 * itself) answers 404 there. The LMS never acts on a payload: it re-reads the order through `fetchOrder()`.
 */
interface ReportsOrderEvents
{
}
