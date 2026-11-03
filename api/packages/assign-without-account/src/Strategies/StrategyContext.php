<?php

namespace Ulams\AssignWithoutAccount\Strategies;

use Ulams\Core\Support\MorphMap;
use Ulams\Cart\Facades\Shop;
use Ulams\AssignWithoutAccount\Strategies\Contracts\AssignStrategy;
use Ulams\Cart\Contracts\Productable;
use Ulams\Cart\Models\Product;

class StrategyContext
{
    private ?AssignStrategy $assignStrategy;

    public function __construct(string $type)
    {
        $type = self::resolve($type);
        if (is_a($type, Product::class, true)) {
            $this->assignStrategy = app(AssignProductStrategy::class);
        }
        else if (is_a($type, Productable::class, true)) {
            $this->assignStrategy = app(AssignProductableStrategy::class);
        }
    }

    /** The class a submission's type stands for: Product, or the registered productable behind an alias or a base class name. */
    public static function resolve(string $type): string
    {
        $class = MorphMap::classOf($type);
        if (!is_string($class) || !class_exists($class) || is_a($class, Product::class, true)) {
            return (string) $class;
        }
        try {
            return Shop::canonicalProductableClass($class) ?? $class;
        } catch (\InvalidArgumentException) {
            return $class;
        }
    }

    public function getAssignStrategy(): ?AssignStrategy
    {
        return $this->assignStrategy;
    }
}
