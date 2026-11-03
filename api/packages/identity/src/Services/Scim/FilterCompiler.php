<?php

namespace Ulams\Identity\Services\Scim;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Turns a parsed filter (FilterParser) into `where` clauses on an Eloquent query. Attributes are looked up in a fixed
 * map of the resource, so a column name never comes from the client; every client value is a bound parameter (the
 * only literals in SQL are `IS TRUE` / `IS FALSE` / `1 = 1` / `1 = 0`). Unsupported attributes or operators are an
 * `invalidFilter` error.
 *
 * Attribute map entries (key: lower-case path, `emails.value`): `col` (SQL expression), `type`
 * (string | int | bool | datetime | const | member), `value` (const only). `string` compares case-insensitively,
 * as SCIM's default (`caseExact` false); give `exact => true` for case-exact attributes.
 */
final class FilterCompiler
{
    private const SCHEMA_PREFIXES = [
        'urn:ietf:params:scim:schemas:extension:enterprise:2.0:user:',
        'urn:ietf:params:scim:schemas:core:2.0:user:',
        'urn:ietf:params:scim:schemas:core:2.0:group:',
    ];

    /** @param array<string,array<string,mixed>> $attributes */
    public function __construct(private readonly array $attributes)
    {
    }

    /** @param array<string,mixed> $node */
    public function apply(Builder $query, array $node): void
    {
        $query->where($this->compile($node, ''));
    }

    /** @param array<string,mixed> $node */
    private function compile(array $node, string $scope): Closure
    {
        switch ($node['t']) {
            case 'and':
                $l = $this->compile($node['l'], $scope);
                $r = $this->compile($node['r'], $scope);

                return function ($w) use ($l, $r): void {
                    $w->where($l);
                    $w->where($r);
                };
            case 'or':
                $l = $this->compile($node['l'], $scope);
                $r = $this->compile($node['r'], $scope);

                return function ($w) use ($l, $r): void {
                    $w->where($l);
                    $w->orWhere($r);
                };
            case 'not':
                $n = $this->compile($node['n'], $scope);

                return fn ($w) => $w->whereNot($n);
            case 'path':
                if ($scope !== '') {
                    throw ScimError::invalidFilter('Value path filters cannot be nested.');
                }
                $prefix = $this->normalise($node['attr']);
                if (!$this->hasChildren($prefix)) {
                    throw ScimError::invalidFilter('Filtering on "' . $this->clip($node['attr']) . '" with a value path is not supported.');
                }

                return $this->compile($node['filter'], $prefix . '.');
            case 'pr':
                $spec = $this->spec($scope . $this->normalise($node['attr']), $node['attr']);

                return $this->present($spec);
            default:
                $spec = $this->spec($scope . $this->normalise($node['attr']), $node['attr']);

                return $this->compare($spec, $node['op'], $node['value'], $node['attr']);
        }
    }

    private function normalise(string $attr): string
    {
        $lower = strtolower($attr);
        foreach (self::SCHEMA_PREFIXES as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return substr($lower, strlen($prefix));
            }
        }

        return $lower;
    }

    private function hasChildren(string $prefix): bool
    {
        foreach (array_keys($this->attributes) as $key) {
            if (str_starts_with($key, $prefix . '.')) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string,mixed> */
    private function spec(string $path, string $original): array
    {
        return $this->attributes[$path] ?? throw ScimError::invalidFilter('Filtering on "' . $this->clip($original) . '" is not supported.');
    }

    /** @param array<string,mixed> $spec */
    private function present(array $spec): Closure
    {
        return match ($spec['type']) {
            'string' => fn ($w) => $w->whereRaw("({$spec['col']} IS NOT NULL AND {$spec['col']} <> '')"),
            'member' => fn ($w) => $w->whereRaw('EXISTS (SELECT 1 FROM group_user gu WHERE gu.group_id = groups.id)'),
            'const' => $this->constant($spec['value'] !== null && $spec['value'] !== ''),
            default => fn ($w) => $w->whereRaw("{$spec['col']} IS NOT NULL"),
        };
    }

    /** @param array<string,mixed> $spec */
    private function compare(array $spec, string $op, mixed $value, string $original): Closure
    {
        $col = $spec['col'] ?? null;
        switch ($spec['type']) {
            case 'const':
                return $this->constant($this->evaluate($spec['value'], $op, $value, $original));
            case 'bool':
                if (!is_bool($value) || !in_array($op, ['eq', 'ne'], true)) {
                    throw ScimError::invalidFilter('"' . $this->clip($original) . '" is a boolean: use eq or ne with true or false.');
                }
                $isTrue = ($op === 'eq') === $value;

                return fn ($w) => $w->whereRaw($isTrue ? "{$col} IS TRUE" : "{$col} IS NOT TRUE");
            case 'member':
                if (!in_array($op, ['eq', 'ne'], true)) {
                    throw ScimError::invalidFilter('Members can be filtered with eq and ne only.');
                }
                $id = $this->intValue($value);
                if ($id === null) {
                    return $this->constant($op === 'ne');
                }
                $not = $op === 'ne' ? 'NOT ' : '';

                return fn ($w) => $w->whereRaw("{$not}EXISTS (SELECT 1 FROM group_user gu WHERE gu.group_id = groups.id AND gu.user_id = ?)", [$id]);
            case 'int':
                if ($value === null && in_array($op, ['eq', 'ne'], true)) {
                    return fn ($w) => $w->whereRaw($op === 'eq' ? "{$col} IS NULL" : "{$col} IS NOT NULL");
                }
                $int = $this->intValue($value);
                if (in_array($op, ['co', 'sw', 'ew'], true)) {
                    throw ScimError::invalidFilter('"' . $this->clip($original) . '" is a number: co, sw and ew do not apply.');
                }
                if ($int === null) {
                    return $this->constant($op === 'ne');
                }

                return $this->binary($col, $op, $int);
            case 'datetime':
                if (in_array($op, ['co', 'sw', 'ew'], true)) {
                    throw ScimError::invalidFilter('"' . $this->clip($original) . '" is a date: co, sw and ew do not apply.');
                }
                if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}(?:[Tt ]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?:[Zz]|[+-]\d{2}:?\d{2})?)?$/', $value)) {
                    throw ScimError::invalidFilter('"' . $this->clip($original) . '" needs an ISO 8601 date-time in quotes.');
                }
                try {
                    $at = Carbon::parse($value)->setTimezone((string) config('app.timezone', 'UTC'))->format('Y-m-d H:i:s');
                } catch (\Throwable) {
                    throw ScimError::invalidFilter('"' . $this->clip($original) . '" needs an ISO 8601 date-time.');
                }

                return $this->binary($col, $op, $at);
            default: // string
                if ($value === null && in_array($op, ['eq', 'ne'], true)) {
                    return fn ($w) => $w->whereRaw($op === 'eq' ? "{$col} IS NULL" : "{$col} IS NOT NULL");
                }
                if (!is_string($value)) {
                    throw ScimError::invalidFilter('"' . $this->clip($original) . '" is a string: put the value in quotes.');
                }
                $exact = (bool) ($spec['exact'] ?? false);
                $lhs = $exact ? $col : "LOWER({$col})";
                $needle = $exact ? $value : mb_strtolower($value);

                return match ($op) {
                    'eq' => fn ($w) => $w->whereRaw("{$lhs} = ?", [$needle]),
                    'ne' => fn ($w) => $w->whereRaw("({$lhs} <> ? OR {$col} IS NULL)", [$needle]),
                    'co' => fn ($w) => $w->whereRaw("{$lhs} LIKE ? ESCAPE '!'", ['%' . $this->like($needle) . '%']),
                    'sw' => fn ($w) => $w->whereRaw("{$lhs} LIKE ? ESCAPE '!'", [$this->like($needle) . '%']),
                    'ew' => fn ($w) => $w->whereRaw("{$lhs} LIKE ? ESCAPE '!'", ['%' . $this->like($needle)]),
                    default => $this->binary($lhs, $op, $needle),
                };
        }
    }

    private function binary(string $lhs, string $op, mixed $value): Closure
    {
        $sql = match ($op) {
            'eq' => '=', 'ne' => '<>', 'gt' => '>', 'ge' => '>=', 'lt' => '<', 'le' => '<=',
            default => throw ScimError::invalidFilter('This operator does not apply to the attribute.'),
        };
        if ($op === 'ne') {
            return fn ($w) => $w->whereRaw("({$lhs} <> ? OR {$lhs} IS NULL)", [$value]);
        }

        return fn ($w) => $w->whereRaw("{$lhs} {$sql} ?", [$value]);
    }

    private function constant(bool $result): Closure
    {
        return fn ($w) => $w->whereRaw($result ? '1 = 1' : '1 = 0');
    }

    /** Evaluates a comparison against an attribute that has one fixed value (an e-mail's `type` is always "work"). */
    private function evaluate(mixed $actual, string $op, mixed $value, string $original): bool
    {
        if (is_bool($actual)) {
            if (!is_bool($value) || !in_array($op, ['eq', 'ne'], true)) {
                throw ScimError::invalidFilter('"' . $this->clip($original) . '" is a boolean: use eq or ne with true or false.');
            }

            return $op === 'eq' ? $actual === $value : $actual !== $value;
        }
        if (!is_string($value)) {
            throw ScimError::invalidFilter('"' . $this->clip($original) . '" is a string: put the value in quotes.');
        }
        $a = mb_strtolower((string) $actual);
        $v = mb_strtolower($value);

        return match ($op) {
            'eq' => $a === $v, 'ne' => $a !== $v,
            'co' => str_contains($a, $v), 'sw' => str_starts_with($a, $v), 'ew' => str_ends_with($a, $v),
            'gt' => $a > $v, 'ge' => $a >= $v, 'lt' => $a < $v, 'le' => $a <= $v,
            default => false,
        };
    }

    private function intValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        return is_string($value) && preg_match('/^[0-9]{1,18}$/', $value) ? (int) $value : null;
    }

    private function like(string $needle): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $needle);
    }

    private function clip(string $s): string
    {
        return mb_substr($s, 0, 60);
    }
}
