<?php

namespace Ulams\Compliance\Services;

use Illuminate\Support\Facades\Schema;
use Ulams\Compliance\Models\PathEnrollment;
use Ulams\Compliance\Models\Rule;
use Ulams\Core\Models\User;

/**
 * "A user with attribute X is enrolled in path Y, due in N days." Rules are evaluated when a user is
 * saved and by the daily run (which also catches group changes). A user is enrolled in a path once, so
 * a rule never re-enrols someone who finished it or was removed by hand.
 */
class RuleEngine
{
    public const OPERATORS = ['equals', 'not_equals', 'in', 'contains', 'starts_with', 'ends_with', 'exists'];

    private static ?bool $hasTable = null;

    public function __construct(private AttributeResolver $attributes, private PathEnrollmentService $enrollments)
    {
    }

    /** Whether every condition of the rule holds for the user (a rule without conditions matches nobody). */
    public function matches(User $user, Rule $rule): bool
    {
        $conditions = $rule->conditions ?? [];
        if ($conditions === []) {
            return false;
        }
        foreach ($conditions as $condition) {
            if (!$this->conditionHolds($user, $condition)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Active rules for the user, or only `$only` (explicitly run by an admin, active or not).
     *
     * @return int number of new enrolments
     */
    public function evaluateUser(User $user, ?Rule $only = null): int
    {
        if (!$this->available()) {
            return 0;
        }
        $created = 0;
        Rule::query()->when($only, fn ($query) => $query->whereKey($only->getKey()), fn ($query) => $query->where('active', true))->with('path')->get()->each(function (Rule $rule) use ($user, &$created) {
            if (!$rule->path || !$this->matches($user, $rule) || $this->enrolled($user, $rule)) {
                return;
            }
            $this->enrollments->enroll($user, $rule->path, $rule->due_days ? now()->addDays($rule->due_days) : null, 'rule', $rule->getKey());
            $created++;
        });

        return $created;
    }

    /** @return int number of new enrolments */
    public function evaluateAll(): int
    {
        if (!$this->available() || !Rule::query()->where('active', true)->exists()) {
            return 0;
        }
        $created = 0;
        $model = config('auth.providers.users.model');
        $model::query()->orderBy('id')->chunkById(200, function ($users) use (&$created) {
            foreach ($users as $user) {
                $created += $this->evaluateUser($user);
            }
        });

        return $created;
    }

    /**
     * Who the rule would enrol now: users that match and are not yet in the path.
     *
     * @return array{count: int, sample: list<array{id: int, name: string}>}
     */
    public function preview(Rule $rule, int $sample = 20): array
    {
        $count = 0;
        $users = [];
        $model = config('auth.providers.users.model');
        $model::query()->orderBy('id')->chunkById(200, function ($chunk) use ($rule, $sample, &$count, &$users) {
            foreach ($chunk as $user) {
                if ($this->matches($user, $rule) && !$this->enrolled($user, $rule)) {
                    $count++;
                    if (count($users) < $sample) {
                        $users[] = ['id' => $user->getKey(), 'name' => $user->name];
                    }
                }
            }
        });

        return ['count' => $count, 'sample' => $users];
    }

    private function enrolled(User $user, Rule $rule): bool
    {
        return PathEnrollment::query()->where('user_id', $user->getKey())->where('path_id', $rule->path_id)->exists();
    }

    private function available(): bool
    {
        return self::$hasTable ??= Schema::hasTable('compliance_rules');
    }

    /** @param array{attribute: string, operator: string, value?: mixed} $condition */
    private function conditionHolds(User $user, array $condition): bool
    {
        $values = $this->attributes->values($user, (string) ($condition['attribute'] ?? ''));
        $operator = $condition['operator'] ?? 'equals';
        $expected = array_map(fn ($v) => mb_strtolower(trim((string) $v)), (array) ($condition['value'] ?? []));

        return match ($operator) {
            'exists' => $values !== [],
            'equals', 'in' => array_intersect($values, $expected) !== [],
            'not_equals' => array_intersect($values, $expected) === [],
            'contains' => $this->any($values, $expected, fn (string $v, string $e) => str_contains($v, $e)),
            'starts_with' => $this->any($values, $expected, fn (string $v, string $e) => str_starts_with($v, $e)),
            'ends_with' => $this->any($values, $expected, fn (string $v, string $e) => str_ends_with($v, $e)),
            default => false,
        };
    }

    /** @param list<string> $values @param list<string> $expected */
    private function any(array $values, array $expected, callable $test): bool
    {
        foreach ($values as $value) {
            foreach ($expected as $needle) {
                if ($needle !== '' && $test($value, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }
}
