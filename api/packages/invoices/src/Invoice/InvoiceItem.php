<?php

namespace Ulams\Invoices\Invoice;

/**
 * One invoice line. Amounts are in major currency units (for example 12.50).
 *
 * The line total is computed as: price × quantity, minus the discount (an amount),
 * plus tax (a percentage of the discounted amount). Each step is rounded to the
 * currency's decimals.
 */
class InvoiceItem
{
    public function __construct(
        public readonly string $title,
        public readonly float $pricePerUnit,
        public readonly float $quantity = 1.0,
        public readonly float $discount = 0.0,
        public readonly float $taxPercentage = 0.0,
        public readonly ?string $description = null,
    ) {
    }

    public function hasDiscount(): bool
    {
        return $this->discount != 0.0;
    }

    public function hasTax(): bool
    {
        return $this->taxPercentage != 0.0;
    }

    public function netBeforeDiscount(int $decimals): float
    {
        return round($this->pricePerUnit * $this->quantity, $decimals);
    }

    public function discountAmount(int $decimals): float
    {
        return round($this->discount, $decimals);
    }

    /**
     * Net amount after the discount, before tax.
     */
    public function netAmount(int $decimals): float
    {
        return round($this->netBeforeDiscount($decimals) - $this->discountAmount($decimals), $decimals);
    }

    public function taxAmount(int $decimals): float
    {
        return round($this->subTotal($decimals) - $this->netAmount($decimals), $decimals);
    }

    /**
     * Gross line total: net amount after discount, plus tax.
     */
    public function subTotal(int $decimals): float
    {
        return round($this->netAmount($decimals) * (1 + $this->taxPercentage / 100), $decimals);
    }
}
