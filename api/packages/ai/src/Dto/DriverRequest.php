<?php

namespace Ulams\Ai\Dto;

/**
 * What a driver sends: the request resolved against the task profile, plus any repair turns.
 */
final class DriverRequest
{
    /**
     * @param ContentBlock[] $blocks
     * @param array<int,array{role:string,text:string}> $turns follow-up turns (repair attempt)
     * @param array<string,mixed> $schema
     */
    public function __construct(
        public readonly string $task,
        public readonly string $promptId,
        public readonly int $promptVersion,
        public readonly string $profile,
        public readonly string $model,
        public readonly string $effort,
        public readonly int $maxTokens,
        public readonly string $system,
        public readonly array $blocks,
        public readonly array $schema,
        public readonly string $cacheTtl = '1h',
        public readonly ?string $fallbacks = null,
        public readonly array $turns = [],
    ) {
    }

    /** @param array<int,array{role:string,text:string}> $turns */
    public function withTurns(array $turns): self
    {
        return new self(
            $this->task,
            $this->promptId,
            $this->promptVersion,
            $this->profile,
            $this->model,
            $this->effort,
            $this->maxTokens,
            $this->system,
            $this->blocks,
            $this->schema,
            $this->cacheTtl,
            $this->fallbacks,
            $turns,
        );
    }

    /** Rough token estimate of the input (4 characters per token), used for budget checks. */
    public function estimatedInputTokens(): int
    {
        $chars = strlen($this->system);
        foreach ($this->blocks as $block) {
            $chars += $block->isDocument() ? (int) (strlen((string) $block->data) * 0.75 / 2) : strlen($block->text);
        }
        foreach ($this->turns as $turn) {
            $chars += strlen($turn['text']);
        }

        return (int) ceil($chars / 4);
    }
}
