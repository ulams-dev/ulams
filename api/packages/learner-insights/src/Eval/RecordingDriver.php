<?php

namespace Ulams\LearnerInsights\Eval;

use Ulams\Ai\Contracts\LlmDriver;
use Ulams\Ai\Dto\DriverRequest;
use Ulams\Ai\Dto\DriverResponse;

/**
 * Wraps the real or fake driver during an eval run and keeps every request, so the data
 * minimisation check can read exactly what would have left the platform. Nothing else changes.
 */
final class RecordingDriver implements LlmDriver
{
    /** @var DriverRequest[] */
    public array $requests = [];

    public function __construct(private readonly LlmDriver $inner)
    {
    }

    public function send(DriverRequest $request): DriverResponse
    {
        $this->requests[] = $request;

        return $this->inner->send($request);
    }

    public function stream(DriverRequest $request, callable $onText): DriverResponse
    {
        $this->requests[] = $request;

        return $this->inner->stream($request, $onText);
    }

    public function name(): string
    {
        return $this->inner->name();
    }

    /** Everything a request carries to the model, as one string. */
    public static function text(DriverRequest $request): string
    {
        $text = $request->system;
        foreach ($request->blocks as $block) {
            $text .= "\n" . (string) $block->text;
        }
        foreach ($request->turns as $turn) {
            $text .= "\n" . $turn['text'];
        }

        return $text;
    }
}
