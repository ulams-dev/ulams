<?php

namespace Ulams\Core\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Ulams\Core\Models\Casts\MorphType;
use LogicException;

/**
 * Stable names for every class that is stored in a polymorphic column (`topicable_type`, `buyable_type`,
 * `model_type`, ...). The database holds the alias, never the class name, so renaming or moving a class
 * cannot orphan rows. Each package registers its own aliases from its service provider; the core provider
 * enforces the map once every provider has booted (`Relation::enforceMorphMap`), so a model that is not in
 * the map fails loudly instead of writing its class name into the database.
 *
 * Aliases are lower-case, dot or underscore separated and never change: `topic.rich_text`, `course`, `user`.
 * A class that extends a mapped class (an application model that extends a package model) uses the alias of
 * its nearest mapped ancestor.
 */
final class MorphMap
{
    /** Every user class (the configured model and its subclasses) is stored as this one alias. */
    public const USER = 'user';

    /** @var array<string, class-string> */
    private static array $aliases = [];

    /**
     * Also hands the aliases to Eloquent, so relations resolve them as soon as the package has registered.
     *
     * @param array<string, class-string> $aliases alias => class
     */
    public static function register(array $aliases): void
    {
        foreach ($aliases as $alias => $class) {
            if (!preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)*$/', (string) $alias)) {
                throw new LogicException("Morph alias [{$alias}] must be lower-case words separated by dots or underscores.");
            }
            if (isset(self::$aliases[$alias]) && self::$aliases[$alias] !== $class) {
                throw new LogicException(sprintf('Morph alias [%s] is already used by %s, it cannot also name %s.', $alias, self::$aliases[$alias], $class));
            }
            self::$aliases[$alias] = $class;
        }

        self::guardDuplicates();
        Relation::morphMap($aliases);
    }

    /**
     * Replaces the class behind an alias. Only for a class that comes from configuration (the user model): the
     * alias itself never changes, only which class it resolves to in this application.
     *
     * @param class-string $class
     */
    public static function replace(string $alias, string $class): void
    {
        self::$aliases[$alias] = $class;
        self::guardDuplicates();
        Relation::morphMap([$alias => $class]);
    }

    /** @return array<string, class-string> alias => class */
    public static function all(): array
    {
        return self::$aliases;
    }

    private static function guardDuplicates(): void
    {
        $duplicates = array_keys(array_filter(array_count_values(self::$aliases), fn (int $count) => $count > 1));
        if ($duplicates !== []) {
            throw new LogicException('A class has more than one morph alias: ' . implode(', ', $duplicates));
        }
    }

    /** The alias of a mapped class, or of its nearest mapped ancestor; null when neither is mapped. */
    public static function aliasOf(string $class): ?string
    {
        $aliases = array_flip(self::$aliases);
        if (isset($aliases[$class])) {
            return $aliases[$class];
        }
        if (!class_exists($class)) {
            return null;
        }
        if (is_a($class, \Ulams\Core\Models\User::class, true)) {
            return self::USER; // the configured user model is mapped, its base class and siblings are the same table
        }
        foreach (class_parents($class) ?: [] as $parent) {
            if (isset($aliases[$parent])) {
                return $aliases[$parent];
            }
        }

        return null;
    }

    /** What `getMorphClass()` stores: the alias, or the class name when the class is not mapped. */
    public static function aliasOrClass(string $class): string
    {
        return self::aliasOf($class) ?? $class;
    }

    /** The class behind an alias. A class name (or any string that is not an alias) is returned unchanged. */
    public static function classOf(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return self::all()[$value] ?? $value;
    }

    /** What to store for a value that is an alias or a class name: the alias when the class is mapped, otherwise the value. */
    public static function canonical(?string $value): ?string
    {
        if ($value === null || $value === '' || isset(self::all()[$value])) {
            return $value;
        }

        return self::aliasOf($value) ?? $value;
    }

    /**
     * The value to compare a column with in a query. Raw queries bypass the model casts, so a `*_type` column that the
     * model casts with MorphType is compared with the alias even when the caller holds a class name.
     */
    public static function queryValue(EloquentBuilder $query, string $column, mixed $value): mixed
    {
        $name = str_contains($column, '.') ? substr($column, (int) strrpos($column, '.') + 1) : $column;
        if (is_string($value) && $query->getModel()->hasCast($name, [MorphType::class])) {
            return self::canonical($value);
        }

        return $value;
    }

    /** Enforce the map: a model whose class is not mapped cannot be stored polymorphically. */
    public static function enforce(): void
    {
        Relation::enforceMorphMap(self::all());
    }

    /** For tests only: forget the aliases and stop enforcing. */
    public static function flush(): void
    {
        self::$aliases = [];
        Relation::requireMorphMap(false);
        Relation::morphMap([], false);
    }
}
