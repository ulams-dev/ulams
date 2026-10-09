<?php

namespace Ulams\Ai\Services;

use Ulams\Ai\Dto\Usage;

/**
 * Cost in micro-USD from the config price table (USD per million tokens = micro-USD per token).
 * Cache writes cost the input price × 1.25 (5 min TTL) or × 2 (1 h TTL); cache reads use their own
 * price. A `long_context` tier replaces all prices when the request's input side exceeds `above`.
 * Costs are computed once at call time and stored; they are never recomputed.
 */
final class CostCalculator
{
    /** @param array<string,array<string,mixed>> $prices @param array<string,float> $writeMultipliers */
    public function __construct(
        private readonly array $prices,
        private readonly array $writeMultipliers = ['5m' => 1.25, '1h' => 2.0],
    ) {
    }

    public static function fromConfig(): self
    {
        return new self((array) config('ai.prices', []), (array) config('ai.cache_write_multipliers', ['5m' => 1.25, '1h' => 2.0]));
    }

    public function knows(string $model): bool
    {
        return $this->priceFor($model) !== null;
    }

    public function cost(string $model, Usage $usage): int
    {
        $price = $this->priceFor($model);
        if ($price === null) {
            return 0;
        }
        if (isset($price['long_context']) && $usage->totalInputTokens() > (int) $price['long_context']['above']) {
            $price = array_merge($price, $price['long_context']);
        }
        $input = (float) $price['input'];
        $micro = $usage->inputTokens * $input
            + $usage->outputTokens * (float) $price['output']
            + $usage->cacheReadTokens * (float) ($price['cache_read'] ?? $input * 0.1)
            + $usage->cacheWrite5mTokens * $input * (float) ($this->writeMultipliers['5m'] ?? 1.25)
            + $usage->cacheWrite1hTokens * $input * (float) ($this->writeMultipliers['1h'] ?? 2.0);

        return (int) round($micro);
    }

    /** Cost estimate before a call: input at the input price, output at the output price. */
    public function estimate(string $model, int $inputTokens, int $outputTokens): int
    {
        return $this->cost($model, new Usage($inputTokens, $outputTokens));
    }

    /** @return array<string,mixed>|null */
    private function priceFor(string $model): ?array
    {
        if (isset($this->prices[$model])) {
            return $this->prices[$model];
        }
        // served model ids may carry a suffix (e.g. a dated snapshot); match by prefix
        foreach ($this->prices as $name => $price) {
            if (str_starts_with($model, $name)) {
                return $price;
            }
        }

        return null;
    }
}
