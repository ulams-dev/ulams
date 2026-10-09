<?php

namespace Ulams\CourseBuilder\Ingestion;

/**
 * Normalised source: cleaned Markdown, metadata and fragments (ids assigned by the ingestor).
 */
final class SourceDocument
{
    /**
     * @param array<int,array{heading_path:string[],section:?string,level:int,ordinal:int,text:string,char_start:int,char_end:int,page_start:?int,page_end:?int,token_estimate:int}> $fragments
     * @param array<string,mixed> $metadata
     */
    public function __construct(
        public readonly string $markdown,
        public readonly array $fragments,
        public readonly array $metadata,
    ) {
    }
}
