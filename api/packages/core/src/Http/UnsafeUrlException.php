<?php

namespace Ulams\Core\Http;

use RuntimeException;

/** A URL or response the safe HTTP client refused (SSRF protection, size cap). The message is safe to show. */
class UnsafeUrlException extends RuntimeException
{
}
