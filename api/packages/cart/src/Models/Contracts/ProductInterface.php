<?php

namespace Ulams\Cart\Models\Contracts;

use Ulams\Cart\Models\Contracts\Base\Buyable;
use Ulams\Cart\Models\Contracts\Base\Taxable;

interface ProductInterface extends Buyable, Taxable
{
}
