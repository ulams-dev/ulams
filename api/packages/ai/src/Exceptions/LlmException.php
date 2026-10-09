<?php

namespace Ulams\Ai\Exceptions;

use RuntimeException;

/**
 * A failed generation step. `reason` is machine-readable; the message is safe to show the author.
 */
class LlmException extends RuntimeException
{
    public const REFUSAL = 'refusal';
    public const MAX_TOKENS = 'max_tokens';
    public const INVALID_OUTPUT = 'invalid_output';
    public const TRANSPORT = 'transport';
    public const DISABLED = 'disabled';
    public const BUDGET = 'budget';
    public const MISSING_CASSETTE = 'missing_cassette';

    /**
     * @param string[] $errors validation errors of the last attempt
     * @param string[] $callIds
     */
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly array $errors = [],
        public readonly array $callIds = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
