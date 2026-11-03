<?php

namespace Ulams\Core\Models;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Ulams\Core\Models\Casts\MorphType;
use Ulams\Core\Support\MorphMap;

/**
 * An Eloquent builder that compares a polymorphic `*_type` column (one the model casts with MorphType) with the
 * morph alias even when the caller passes a class name: `Topic::where('topicable_type', RichText::class)`.
 */
class MorphTypeBuilder extends Builder
{
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if (is_string($column) && $this->isMorphType($column)) {
            if (func_num_args() === 2) {
                $operator = $this->alias($operator);
            } else {
                $value = $this->alias($value);
            }
        } elseif (is_array($column)) {
            $column = $this->aliasPairs($column);
        }

        return parent::where($column, $operator, $value, $boolean);
    }

    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        if (is_string($column) && $this->isMorphType($column) && is_array($values)) {
            $values = array_map(fn ($value) => $this->alias($value), $values);
        }

        return parent::whereIn($column, $values, $boolean, $not);
    }

    private function isMorphType(string $column): bool
    {
        $name = str_contains($column, '.') ? substr($column, (int) strrpos($column, '.') + 1) : $column;
        $table = str_contains($column, '.') ? substr($column, 0, (int) strrpos($column, '.')) : null;

        return ($table === null || $table === $this->getModel()->getTable())
            && $this->getModel()->hasCast($name, [MorphType::class]);
    }

    private function alias(mixed $value): mixed
    {
        return is_string($value) ? MorphMap::canonical($value) : $value;
    }

    /** @param array<array-key, mixed> $pairs */
    private function aliasPairs(array $pairs): array
    {
        foreach ($pairs as $key => $value) {
            if (is_string($key) && $this->isMorphType($key)) {
                $pairs[$key] = $this->alias($value);
            }
        }

        return $pairs;
    }
}
