<?php

namespace Ulams\Tutor\Streaming;

use Closure;

/**
 * Coalesces model text deltas so a run writes tens of event rows, not one per token (ADR 0105): the buffer
 * is emitted at the end of a sentence or line, or once `$intervalMs` have passed since the last emit.
 */
final class DeltaBuffer
{
    private string $buffer = '';

    private float $lastEmit;

    /** @param Closure(string):void $emit @param (Closure():float)|null $clock milliseconds, injectable for tests */
    public function __construct(private readonly Closure $emit, private readonly int $intervalMs = 100, private readonly ?Closure $clock = null)
    {
        $this->lastEmit = $this->now();
    }

    public function push(string $text): void
    {
        $this->buffer .= $text;
        if (preg_match('/[.!?…。！？]["”’)\]]*\s+$|\n$/u', $this->buffer) || $this->now() - $this->lastEmit >= $this->intervalMs) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        if ($this->buffer !== '') {
            ($this->emit)($this->buffer);
            $this->buffer = '';
        }
        $this->lastEmit = $this->now();
    }

    private function now(): float
    {
        return $this->clock !== null ? ($this->clock)() : microtime(true) * 1000;
    }
}
