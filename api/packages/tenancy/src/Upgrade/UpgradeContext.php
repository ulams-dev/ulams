<?php

namespace Ulams\Tenancy\Upgrade;

use Closure;

/**
 * What a step can do for the target it runs for: the platform (in this process) or one tenant
 * (in a child `artisan --domain=<host>` process, so the tenant's own configuration applies).
 */
class UpgradeContext
{
    /**
     * @param Closure(string, array<int|string, mixed>): string $artisan runs a command for the target and returns its output
     */
    public function __construct(
        public readonly string $target,
        public readonly ?string $host,
        private Closure $artisan,
    ) {
    }

    public function isPlatform(): bool
    {
        return $this->host === null;
    }

    /**
     * Runs an artisan command for this target. A non-zero exit throws.
     *
     * @param array<int|string, mixed> $arguments command arguments and options, as for Artisan::call()
     */
    public function artisan(string $command, array $arguments = []): string
    {
        return ($this->artisan)($command, $arguments);
    }
}
