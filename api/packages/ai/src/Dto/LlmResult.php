<?php

namespace Ulams\Ai\Dto;

final class LlmResult
{
    /**
     * @param array<string,mixed> $data the validated structured output
     * @param string[] $callIds ai_calls ids of every attempt
     */
    public function __construct(
        public readonly array $data,
        public readonly string $model,
        public readonly Usage $usage,
        public readonly int $costMicroUsd,
        public readonly int $attempts,
        public readonly array $callIds,
    ) {
    }
}
