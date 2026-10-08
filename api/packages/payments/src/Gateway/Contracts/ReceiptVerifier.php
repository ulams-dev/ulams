<?php

namespace Ulams\Payments\Gateway\Contracts;

use Ulams\Payments\Models\Payment;

/**
 * Server-side verification of an in-app purchase (for example through the RevenueCat REST API).
 *
 * Implementations must check with the store or RevenueCat that the purchase exists, belongs to the payment's
 * user and covers the payment's product and amount. Returning true marks the payment as paid.
 */
interface ReceiptVerifier
{
    public function verify(Payment $payment, array $parameters = []): bool;
}
