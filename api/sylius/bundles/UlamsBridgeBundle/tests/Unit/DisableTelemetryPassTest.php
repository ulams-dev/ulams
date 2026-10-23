<?php

declare(strict_types=1);

namespace Ulams\Bridge\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Ulams\Bridge\DependencyInjection\Compiler\DisableTelemetryPass;

final class DisableTelemetryPassTest extends TestCase
{
    public function testEveryTelemetryServiceIsRemovedAndOthersStay(): void
    {
        $container = new ContainerBuilder();
        foreach (['sylius.telemetry.send_manager', 'sylius.telemetry.sender', 'sylius.telemetry.listener', 'sylius.order_processor', 'ulams.something'] as $id) {
            $container->register($id);
        }
        $container->setAlias('sylius.telemetry.client', 'sylius.telemetry.sender');

        (new DisableTelemetryPass())->process($container);

        foreach (['sylius.telemetry.send_manager', 'sylius.telemetry.sender', 'sylius.telemetry.listener'] as $id) {
            self::assertFalse($container->has($id), $id);
        }
        self::assertFalse($container->hasAlias('sylius.telemetry.client'));
        self::assertTrue($container->has('sylius.order_processor'));
        self::assertTrue($container->has('ulams.something'));
    }

    public function testTheRealContainerCarriesNoTelemetryService(): void
    {
        $dumped = glob(dirname(__DIR__, 4) . '/var/cache/*/App_KernelProdContainer.php');
        if ($dumped === [] || $dumped === false) {
            self::markTestSkipped('No compiled production container in var/cache (run bin/console cache:warmup in prod first).');
        }
        self::assertStringNotContainsString('sylius.telemetry.send_manager', (string) file_get_contents($dumped[0]));
    }
}
