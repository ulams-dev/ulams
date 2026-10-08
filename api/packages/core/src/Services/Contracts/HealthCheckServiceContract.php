<?php

namespace Ulams\Core\Services\Contracts;

interface HealthCheckServiceContract
{
    public function getHealthData(): array;
}
