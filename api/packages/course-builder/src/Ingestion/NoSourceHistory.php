<?php

namespace Ulams\CourseBuilder\Ingestion;

use Ulams\CourseBuilder\Contracts\SourceHistory;

/** The default when Living Course is not installed: no revision history. */
final class NoSourceHistory implements SourceHistory
{
    public function repointed(array $fragmentIds): array
    {
        return [];
    }

    public function passage(string $fragmentId, array $revisionIds): ?array
    {
        return null;
    }
}
