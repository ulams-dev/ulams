<?php

namespace EscolaLms\Reports\Stats\Topic;

class AverageTime extends AbstractTopicStat
{
    public function calculate(): int
    {
        return $this->topic->progress()->average('seconds') ?? 0;
    }
}
