<?php

declare(strict_types=1);

namespace Ulams\Bridge\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Sylius core can be told not to report usage (`sylius_core.telemetry.enabled: false`, which this project sets), but the
 * Stripe and Refund plugins each register a pass whose README says it enforces telemetry in production containers.
 * A commerce service that may run air-gapped and holds customers' orders reports nothing, so this pass, which runs after
 * theirs, removes every `sylius.telemetry.*` service. In the Sylius 2.3.0 container built for this project no such
 * service was loaded even with the default configuration, so today the pass guards against a later Sylius or plugin
 * release rather than changing anything (plan Q12, risk R6); the container test below fails if one ever appears.
 */
final class DisableTelemetryPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach (array_merge(array_keys($container->getDefinitions()), array_keys($container->getAliases())) as $id) {
            if (str_starts_with((string) $id, 'sylius.telemetry.')) {
                $container->removeDefinition((string) $id);
                $container->removeAlias((string) $id);
            }
        }
    }
}
