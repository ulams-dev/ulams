<?php

namespace Ulams\LivingCourse\Connectors;

use RuntimeException;

/** A webhook delivery whose signature did not match the connection's secret. */
class InvalidSignature extends RuntimeException
{
}
