<?php

namespace EscolaLms\Consultations\Strategies\Relations;

use EscolaLms\Consultations\Strategies\Contracts\RelationStrategyContract;

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
