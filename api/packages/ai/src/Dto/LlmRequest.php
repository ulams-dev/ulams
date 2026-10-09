<?php

namespace Ulams\Ai\Dto;

use Closure;
use Ulams\Ai\Prompts\Prompt;

/**
 * A provider-neutral generation request. The model, effort and output limit come from the task's
 * profile in config/ai.php; callers never name a model.
 */
final class LlmRequest
{
    /**
     * @param ContentBlock[] $blocks user turn, stable blocks first
     * @param array<string,mixed> $schema JSON Schema of the structured output
     * @param array{type?:string,id?:string} $subject what the call is for (logging and budgets)
     * @param Closure(array):array<int,string>|null $validator semantic checks; returns error messages
     * @param array{tokens?:int,cost_micro_usd?:int} $budget per-subject caps overriding config
     */
    public function __construct(
        public readonly string $task,
        public readonly Prompt $prompt,
        public readonly array $blocks,
        public readonly array $schema,
        public readonly array $subject = [],
        public readonly ?int $userId = null,
        public readonly ?Closure $validator = null,
        public readonly array $budget = [],
    ) {
    }

    public function subjectType(): ?string
    {
        return $this->subject['type'] ?? null;
    }

    public function subjectId(): ?string
    {
        return isset($this->subject['id']) ? (string) $this->subject['id'] : null;
    }
}
