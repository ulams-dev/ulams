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

    /**
     * Like send(), and calls `$onText` with every text delta as it arrives (raw model output, JSON for
     * structured outputs). Returns the same final response as send(): validation, logging and cost stay in the client.
     *
     * @param callable(string):void $onText
     *
     * @throws DriverException on transport or provider errors
     */
    public function stream(DriverRequest $request, callable $onText): DriverResponse;

    public function name(): string;
}
