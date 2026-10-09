<?php

namespace Ulams\Ai\Fake;

use Closure;
use Ulams\Ai\Dto\DriverRequest;

/**
 * Deterministic stand-ins for the model, registered per task by the packages that own the
 * prompts. Used by the fake driver in `synthetic` mode when no cassette matches (local demos
 * without an API key, the end-to-end test). Never used by the anthropic driver.
 */
final class FakeResponders
{
    /** @var array<string,Closure(DriverRequest):array> */
    private array $responders = [];

    /** @param Closure(DriverRequest):array $responder returns the structured output */
    public function register(string $task, Closure $responder): void
    {
        $this->responders[$task] = $responder;
    }

    public function has(string $task): bool
    {
        return isset($this->responders[$task]);
    }

    /** @return array<string,mixed> */
    public function respond(DriverRequest $request): array
    {
        return ($this->responders[$request->task])($request);
    }
}
