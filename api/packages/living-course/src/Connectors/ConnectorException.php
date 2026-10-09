<?php

namespace Ulams\LivingCourse\Connectors;

use RuntimeException;

/** A connector could not fetch or validate. The message is safe to show the author and never carries a token. */
class ConnectorException extends RuntimeException
{
}
