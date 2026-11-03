<?php

namespace Ulams\Ai\Contracts;

use Ulams\Ai\Dto\LlmRequest;
use Ulams\Ai\Dto\LlmResult;
use Ulams\Ai\Exceptions\LlmException;

interface LlmClient
{
    /**
     * Generates a structured output for the request: validated against its JSON Schema and the
     * request's semantic validator, with one repair attempt. Every attempt is logged to ai_calls.
     *
     * @throws LlmException when the model refuses, runs out of tokens, the output stays invalid,
     *                      the budget is exhausted or AI is disabled
     */
    public function generate(LlmRequest $request): LlmResult;

    /**
     * Same contract as generate() (one ai_calls row per model call with model, tokens and cost, validation,
     * one repair attempt), and calls `$onDelta` with the text of the string field `$field` of the structured
     * output as the model writes it. Deltas come from the first attempt only and are a draft: the returned
     * result is the only authoritative output. The field must be a top-level string of the schema.
     *
     * @param callable(string):void $onDelta
     *
     * @throws LlmException as generate()
     */
    public function stream(LlmRequest $request, callable $onDelta, string $field = 'answer'): LlmResult;

    /** False when AI_DRIVER=disabled (or no API key): builder endpoints answer 503. */
    public function enabled(): bool;

    /** Display label of the profile used by a task (never a model id). */
    public function profileLabel(string $task): string;
}
