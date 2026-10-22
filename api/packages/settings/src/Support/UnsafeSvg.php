<?php

namespace Ulams\Settings\Support;

use RuntimeException;

/** The SVG cannot be made safe to serve; the message says what was found, for the admin who uploaded it. */
final class UnsafeSvg extends RuntimeException
{
}
