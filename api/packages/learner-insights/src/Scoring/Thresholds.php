<?php

namespace Ulams\LearnerInsights\Scoring;

/**
 * Rule thresholds: the package defaults (config `learner_insights.rules`), overridden by the tenant
 * settings (the same config keys, administrable) and then by the course.
 */
final class Thresholds
{
    /** @param array<string,array<string,mixed>> $rules */
    public function __construct(private readonly array $rules)
    {
    }

    /** @param array<string,array<string,mixed>> $courseOverrides */
    public static function make(array $tenant, array $courseOverrides = []): self
    {
        $rules = $tenant;
        foreach ($courseOverrides as $rule => $params) {
            if (!is_array($params) || !isset($rules[$rule])) {
                continue;
            }
            $rules[$rule] = array_replace($rules[$rule], array_intersect_key($params, $rules[$rule]));
        }

        return new self($rules);
    }

    public function enabled(string $rule): bool
    {
        return (bool) ($this->rules[$rule]['enabled'] ?? false);
    }

    public function get(string $rule, string $param): int|float
    {
        return $this->rules[$rule][$param];
    }

    /** @return array<string,array<string,mixed>> */
    public function all(): array
    {
        return $this->rules;
    }
}
