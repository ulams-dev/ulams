<?php

namespace Ulams\LivingCourse\Connectors;

/** What a connector found: the remote reference and the files of the new state. */
final class FetchResult
{
    /**
     * @param FetchedFile[] $files
     * @param array<string,mixed> $metadata shown in the UI, never sent to the model
     */
    public function __construct(
        public readonly bool $unchanged,
        public readonly string $ref,
        public readonly array $files = [],
        public readonly array $metadata = [],
    ) {
    }

    public static function unchanged(string $ref): self
    {
        return new self(true, $ref);
    }
}
