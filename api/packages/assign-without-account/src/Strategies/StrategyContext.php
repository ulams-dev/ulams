<?php

namespace EscolaLms\AssignWithoutAccount\Strategies;

use EscolaLms\AssignWithoutAccount\Strategies\Contracts\AssignStrategy;
use EscolaLms\Cart\Contracts\Productable;
use EscolaLms\Cart\Models\Product;

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
