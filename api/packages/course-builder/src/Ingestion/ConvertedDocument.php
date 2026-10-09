<?php

namespace Ulams\CourseBuilder\Ingestion;

/**
 * One file converted to Markdown: the cleaned text, PDF page marks and converter metadata.
 * `path` is set for multi-file sources (repositories, several web pages); it becomes the first
 * element of the heading path of every fragment of the file.
 */
final class ConvertedDocument
{
    /**
     * @param array<int,array{0:int,1:int}> $pageMarks [char offset, page number]
     * @param array<string,mixed> $metadata
     */
    public function __construct(
        public readonly string $markdown,
        public readonly array $pageMarks = [],
        public readonly array $metadata = [],
        public readonly ?string $path = null,
    ) {
    }
}
