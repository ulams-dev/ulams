<?php

namespace Ulams\Commerce\Support;

/** What the LMS knows about a sellable: its buyer-facing name, the courses it grants and whether it may be on sale. */
final class SellableInfo
{
    /** @param list<int> $courseIds */
    public function __construct(
        public readonly string $name,
        public readonly ?string $description,
        public readonly array $courseIds,
        public readonly bool $published,
        public readonly ?int $accessDays = null,
    ) {
    }
}
