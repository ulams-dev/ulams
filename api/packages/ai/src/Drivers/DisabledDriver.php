<?php

namespace Ulams\Ai\Drivers;

use Ulams\Ai\Contracts\LlmDriver;
use Ulams\Ai\Dto\DriverRequest;
use Ulams\Ai\Dto\DriverResponse;
use Ulams\Ai\Exceptions\DriverException;

final class DisabledDriver implements LlmDriver
{
    public function name(): string
    {
        return 'disabled';
    }

    public function send(DriverRequest $request): DriverResponse
    {
        throw new DriverException('AI is disabled on this installation.');
    }
}
