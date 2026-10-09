<?php

namespace Ulams\Ai\Contracts;

use Ulams\Ai\Dto\DriverRequest;
use Ulams\Ai\Dto\DriverResponse;
use Ulams\Ai\Exceptions\DriverException;

/** One HTTP call to a provider (or a replay). No validation, logging or budgets here. */
interface LlmDriver
{
    /** @throws DriverException on transport or provider errors */
    public function send(DriverRequest $request): DriverResponse;

    public function name(): string;
}
