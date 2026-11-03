<?php

declare(strict_types=1);

namespace Ulams\Bridge;

use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Ulams\Bridge\DependencyInjection\Compiler\DisableTelemetryPass;

/**
 * The ulams bridge (ADR 0131): what stock Sylius lacks for a headless service behind the LMS. The LMS owns access, so
 * the bridge only reports what happened to orders (a transactional outbox, a pull feed and signed delivery) and keeps
 * the per-channel links the LMS provisions.
 */
final class UlamsBridgeBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        // after the plugins' own passes, which load the telemetry services in production
        $container->addCompilerPass(new DisableTelemetryPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, -100);
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
