<?php

namespace Ulams\Commerce\Contracts;

use Ulams\Commerce\Support\ProductRef;
use Ulams\Commerce\Support\ProductState;

/**
 * Optional capability: read a product back, so `ulams:commerce:sync --check` can report drift (a product that was
 * deleted, re-priced or enabled by hand in the provider). Returns null when the provider has no such product.
 */
interface InspectsCatalogue
{
    public function inspectProduct(ProductRef $product): ?ProductState;
}
