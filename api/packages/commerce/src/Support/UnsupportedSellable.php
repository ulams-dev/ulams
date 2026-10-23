<?php

namespace Ulams\Commerce\Support;

use InvalidArgumentException;

/** The provider cannot sell this kind of sellable (retrying will not help). */
class UnsupportedSellable extends InvalidArgumentException
{
}
