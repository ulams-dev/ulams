<?php

namespace Ulams\Webinar\Strategies\Relations;

use Ulams\Webinar\Strategies\Contracts\RelationStrategyContract;

class RelationsStrategy
{
    private RelationStrategyContract $relationStrategyContract;

    public function __construct(
        RelationStrategyContract $relationStrategyContract
    )
    {
        $this->relationStrategyContract = $relationStrategyContract;
    }

    public function setRelation(): void
    {
        $this->relationStrategyContract->setRelation();
    }
}
