<?php

namespace Ulams\Reporting\Support;

use InvalidArgumentException;

/**
 * One column of a report dataset and what a report may do with it. The registry is the only source of column
 * names the query compiler accepts (ADR 0194): a name that is not declared here never reaches SQL.
 */
final class Column
{
    public const STRING = 'string';
    public const INT = 'int';
    public const DECIMAL = 'decimal';
    public const BOOL = 'bool';
    public const DATE = 'date';
    public const DATETIME = 'datetime';
    public const ENUM = 'enum';

    public const TYPES = [self::STRING, self::INT, self::DECIMAL, self::BOOL, self::DATE, self::DATETIME, self::ENUM];

    /** Filter operators every type supports, then the ones that only make sense for it. */
    private const OPERATORS = [
        self::STRING => ['eq', 'neq', 'in', 'not_in', 'contains', 'is_null'],
        self::ENUM => ['eq', 'neq', 'in', 'not_in', 'is_null'],
        self::INT => ['eq', 'neq', 'in', 'not_in', 'lt', 'lte', 'gt', 'gte', 'between', 'is_null'],
        self::DECIMAL => ['eq', 'neq', 'lt', 'lte', 'gt', 'gte', 'between', 'is_null'],
        self::DATE => ['eq', 'lt', 'lte', 'gt', 'gte', 'between', 'is_null'],
        self::DATETIME => ['lt', 'lte', 'gt', 'gte', 'between', 'is_null'],
        self::BOOL => ['eq', 'is_null'],
    ];

    /**
     * @param list<string> $filterOps operators a filter on this column may use
     * @param list<string> $enumValues the allowed values of an enum column
     */
    private function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly string $label,
        public readonly array $filterOps,
        public readonly bool $groupable,
        public readonly bool $aggregatable,
        public readonly bool $personal,
        public readonly array $enumValues,
    ) {
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            throw new InvalidArgumentException("Column name \"{$name}\" must be lower snake case.");
        }
        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException("Column {$name}: unknown type {$type}.");
        }
        if ($type === self::ENUM && $enumValues === []) {
            throw new InvalidArgumentException("Column {$name}: an enum column lists its values.");
        }
        if ($personal && $aggregatable) {
            throw new InvalidArgumentException("Column {$name}: a personal column cannot be aggregated.");
        }
    }

    public static function string(string $name, string $label, bool $personal = false, bool $groupable = true): self
    {
        return new self($name, self::STRING, $label, self::OPERATORS[self::STRING], $groupable && !$personal, false, $personal, []);
    }

    public static function int(string $name, string $label, bool $groupable = false): self
    {
        return new self($name, self::INT, $label, self::OPERATORS[self::INT], $groupable, true, false, []);
    }

    public static function decimal(string $name, string $label): self
    {
        return new self($name, self::DECIMAL, $label, self::OPERATORS[self::DECIMAL], false, true, false, []);
    }

    public static function bool(string $name, string $label): self
    {
        return new self($name, self::BOOL, $label, self::OPERATORS[self::BOOL], true, false, false, []);
    }

    public static function date(string $name, string $label): self
    {
        return new self($name, self::DATE, $label, self::OPERATORS[self::DATE], true, false, false, []);
    }

    public static function datetime(string $name, string $label): self
    {
        return new self($name, self::DATETIME, $label, self::OPERATORS[self::DATETIME], true, false, false, []);
    }

    /** @param list<string> $values */
    public static function enum(string $name, string $label, array $values): self
    {
        return new self($name, self::ENUM, $label, self::OPERATORS[self::ENUM], true, false, false, array_values($values));
    }

    public function isTemporal(): bool
    {
        return $this->type === self::DATE || $this->type === self::DATETIME;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'label' => $this->label,
            'filterOps' => $this->filterOps,
            'groupable' => $this->groupable,
            'aggregatable' => $this->aggregatable,
            'personal' => $this->personal,
            'enumValues' => $this->enumValues,
        ];
    }
}
