<?php

namespace Ulams\Core\Observability;

/**
 * One Prometheus metric family: a name, a help text and samples (`[value, labels]`).
 */
final class Metric
{
    /** @param list<array{0: int|float, 1: array<string, string|int>}> $samples */
    private function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly string $help,
        public readonly array $samples,
    ) {
    }

    /** @param list<array{0: int|float, 1?: array<string, string|int>}> $samples */
    public static function gauge(string $name, string $help, array $samples): self
    {
        return new self($name, 'gauge', $help, array_map(fn (array $s) => [$s[0], $s[1] ?? []], $samples));
    }
}
