<?php

namespace Ulams\Commerce\Support;

use InvalidArgumentException;

/** A thing that can be sold: a course, a bundle of courses, or an access pass (a course or bundle for N days). */
final class SellableRef
{
    public const COURSE = 'course';
    public const BUNDLE = 'bundle';
    /** A course or a bundle sold for a number of days. */
    public const ACCESS_PASS = 'access_pass';

    public const TYPES = [self::COURSE, self::BUNDLE, self::ACCESS_PASS];

    public function __construct(public readonly string $type, public readonly int $id)
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException("Unknown sellable type {$type}");
        }
        if ($id < 1) {
            throw new InvalidArgumentException('A sellable needs an id.');
        }
    }

    public static function course(int $id): self
    {
        return new self(self::COURSE, $id);
    }

    public static function bundle(int $id): self
    {
        return new self(self::BUNDLE, $id);
    }

    public static function accessPass(int $id): self
    {
        return new self(self::ACCESS_PASS, $id);
    }

    public function key(): string
    {
        return $this->type . ':' . $this->id;
    }
}
