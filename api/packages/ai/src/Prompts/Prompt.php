<?php

namespace Ulams\Ai\Prompts;

/**
 * A frozen system prompt: id (`<namespace>/<task>`), version and text. The version is logged with
 * every call and is part of the cassette key, so a prompt change needs new cassettes.
 */
final class Prompt
{
    /** @param array<string,string> $meta front matter */
    public function __construct(
        public readonly string $id,
        public readonly int $version,
        public readonly string $text,
        public readonly array $meta = [],
    ) {
    }

    public static function inline(string $id, int $version, string $text): self
    {
        return new self($id, $version, $text);
    }
}
