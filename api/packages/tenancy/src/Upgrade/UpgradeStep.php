<?php

namespace Ulams\Tenancy\Upgrade;

use Closure;

class UpgradeStep
{
    /**
     * @param Closure(UpgradeContext): (string|null) $callback may return a line of output for the report
     */
    public function __construct(
        public readonly string $name,
        public readonly Closure $callback,
        public readonly ?string $since = null,
        public readonly bool $once = true,
        public readonly string $scope = UpgradeSteps::BOTH,
        public readonly ?string $requiresCommand = null,
    ) {
    }
}
