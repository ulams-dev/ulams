<?php

declare(strict_types=1);

namespace Ulams\Bridge;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * The ulams bridge (ADR 0131): what stock Sylius lacks for a headless service behind the LMS. The LMS owns access, so
 * the bridge only reports what happened to orders (a transactional outbox, a pull feed and signed delivery) and keeps
 * the per-channel links the LMS provisions.
 */
final class UlamsBridgeBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
