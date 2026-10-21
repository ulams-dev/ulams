<?php

namespace Ulams\Commerce\Services;

use RuntimeException;

/** A paid order line has no course behind it. Retried by the job: an admin may repair the product link meanwhile. */
class UnlinkedProduct extends RuntimeException
{
}
