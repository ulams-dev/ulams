<?php

namespace Ulams\Cart\Models\Contracts\Base;

use Treestoneit\ShoppingCart\Taxable as BaseTaxable;

interface Taxable extends BaseTaxable
{
    public function getTaxRate(): float;
}
