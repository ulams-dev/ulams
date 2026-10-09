<?php

namespace Ulams\LivingCourse\Connectors;

/** One file of a fetched state. `kind` is markdown | pdf | docx | html; `blob` identifies the content at the remote (a Git blob id, an ETag). */
final class FetchedFile
{
    public function __construct(
        public readonly string $path,
        public readonly string $bytes,
        public readonly string $kind = 'markdown',
        public readonly ?string $blob = null,
    ) {
    }
}
