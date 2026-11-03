<?php

namespace Ulams\Entitlements\Support;

/** What `AccessParity::check()` found. `sample` holds at most the first N differing pairs. */
final class ParityResult
{
    /**
     * @param array<string,int> $byKind difference kind => count
     * @param list<array<string,mixed>> $sample
     */
    public function __construct(
        public readonly int $pairs,
        public readonly int $differences,
        public readonly array $byKind,
        public readonly array $sample,
    ) {
    }

    public function passed(): bool
    {
        return $this->differences === 0;
    }

    /** @return array<string,mixed> */
    public function details(): array
    {
        return ['by_kind' => $this->byKind, 'sample' => $this->sample];
    }
}
