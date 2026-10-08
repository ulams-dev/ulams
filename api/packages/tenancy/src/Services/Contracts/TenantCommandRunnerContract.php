<?php

namespace Ulams\Tenancy\Services\Contracts;

interface TenantCommandRunnerContract
{
    /**
     * Runs `php artisan <arguments> --domain=<host>` in a separate process, so the command
     * boots with the tenant `.env` instead of the platform one.
     *
     * @param list<string> $arguments
     * @return string combined output
     *
     * @throws \RuntimeException when the command fails
     */
    public function run(string $host, array $arguments): string;
}
