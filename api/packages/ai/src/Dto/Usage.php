<?php

namespace Ulams\Ai\Dto;

final class Usage
{
    public function __construct(
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly int $cacheWrite5mTokens = 0,
        public readonly int $cacheWrite1hTokens = 0,
        public readonly int $cacheReadTokens = 0,
    ) {
    }

    public function cacheWriteTokens(): int
    {
        return $this->cacheWrite5mTokens + $this->cacheWrite1hTokens;
    }

    /** All input-side tokens, cached or not (used for the long-context price tier). */
    public function totalInputTokens(): int
    {
        return $this->inputTokens + $this->cacheWriteTokens() + $this->cacheReadTokens;
    }

    public function plus(self $other): self
    {
        return new self(
            $this->inputTokens + $other->inputTokens,
            $this->outputTokens + $other->outputTokens,
            $this->cacheWrite5mTokens + $other->cacheWrite5mTokens,
            $this->cacheWrite1hTokens + $other->cacheWrite1hTokens,
            $this->cacheReadTokens + $other->cacheReadTokens,
        );
    }

    /** @return array<string,int> */
    public function toArray(): array
    {
        return [
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'cache_write_5m_tokens' => $this->cacheWrite5mTokens,
            'cache_write_1h_tokens' => $this->cacheWrite1hTokens,
            'cache_read_tokens' => $this->cacheReadTokens,
        ];
    }

    /** @param array<string,mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self(
            (int) ($a['input_tokens'] ?? 0),
            (int) ($a['output_tokens'] ?? 0),
            (int) ($a['cache_write_5m_tokens'] ?? 0),
            (int) ($a['cache_write_1h_tokens'] ?? ($a['cache_creation_tokens'] ?? 0)),
            (int) ($a['cache_read_tokens'] ?? 0),
        );
    }
}
