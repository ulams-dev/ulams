<?php

namespace Ulams\Commerce\Support;

use InvalidArgumentException;

/** A thing that can be sold: a course or a bundle of courses. */
final class SellableRef
{
    public const COURSE = 'course';
    public const BUNDLE = 'bundle';

    public function __construct(public readonly string $type, public readonly int $id)
    {
        if (!in_array($type, [self::COURSE, self::BUNDLE], true)) {
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
}
