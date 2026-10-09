<?php

namespace Ulams\CourseBuilder\Ingestion;

use Ulams\CourseBuilder\Contracts\FragmentArchive;

/** Phase 2 behaviour: a fragment that is not in the live table does not exist. */
final class NoFragmentArchive implements FragmentArchive
{
    public function find(string $fragmentId): ?array
    {
        return null;
    }

    public function knownIds(string $sessionId): array
    {
        return [];
    }
}
