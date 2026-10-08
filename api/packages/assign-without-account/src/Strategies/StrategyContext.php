<?php

namespace Ulams\AssignWithoutAccount\Strategies;

use Ulams\AssignWithoutAccount\Strategies\Contracts\AssignStrategy;
use Ulams\Cart\Contracts\Productable;
use Ulams\Cart\Models\Product;

class StrategyContext
{
    private ?AssignStrategy $assignStrategy;

    public function __construct(string $type)
    {
        if (is_a($type, Product::class, true)) {
            $this->assignStrategy = app(AssignProductStrategy::class);
        }
        else if (is_a($type, Productable::class, true)) {
            $this->assignStrategy = app(AssignProductableStrategy::class);
        }
    }

    public function getAssignStrategy(): ?AssignStrategy
    {
        return $this->assignStrategy;
    }
}
