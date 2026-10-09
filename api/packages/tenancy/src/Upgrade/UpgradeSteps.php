<?php

namespace Ulams\Tenancy\Upgrade;

use Closure;

/**
 * Registry of the steps `ulams:upgrade` runs, in registration order. Packages register their own
 * from a service provider:
 *
 *   UpgradeSteps::register('cmi5_move_to_bucket', fn (UpgradeContext $c) => $c->artisan('cmi5:move-to-bucket'), since: '0.4');
 *
 * A step runs once per target (the platform and every tenant) and is then recorded in
 * `tenant_upgrade_steps`, unless it is registered with `once: false` (it then runs on every upgrade,
 * for migrations and other idempotent work). A step whose `requiresCommand` is not registered in this
 * build is skipped and not recorded, so it runs later, on the first upgrade that has the command.
 * A step must be safe to run again (`--force-step=<name>` does so).
 */
class UpgradeSteps
{
    public const BOTH = 'both';
    public const PLATFORM = 'platform';
    public const TENANT = 'tenant';

    /** @var array<string, UpgradeStep> */
    private static array $steps = [];

    /**
     * @param Closure(UpgradeContext): (string|null) $callback
     * @param string|null $since release that introduced the step (shown in the report)
     * @param string $scope `both`, `platform` or `tenant`
     */
    public static function register(
        string $name,
        Closure $callback,
        ?string $since = null,
        bool $once = true,
        string $scope = self::BOTH,
        ?string $requiresCommand = null,
    ): void {
        self::$steps[$name] = new UpgradeStep($name, $callback, $since, $once, $scope, $requiresCommand);
    }

    /**
     * Registers a step that runs one artisan command (the common case).
     *
     * @param array<int|string, mixed> $arguments
     */
    public static function command(
        string $name,
        string $command,
        array $arguments = [],
        ?string $since = null,
        bool $once = true,
        string $scope = self::BOTH,
        bool $requiresCommand = false,
    ): void {
        self::register(
            $name,
            fn (UpgradeContext $context) => $context->artisan($command, $arguments),
            $since,
            $once,
            $scope,
            $requiresCommand ? $command : null,
        );
    }

    /** @return list<UpgradeStep> */
    public static function all(): array
    {
        return array_values(self::$steps);
    }

    /** @return list<UpgradeStep> */
    public static function forScope(bool $platform): array
    {
        return array_values(array_filter(
            self::$steps,
            fn (UpgradeStep $step) => $step->scope === self::BOTH || $step->scope === ($platform ? self::PLATFORM : self::TENANT)
        ));
    }

    public static function flush(): void
    {
        self::$steps = [];
    }
}
