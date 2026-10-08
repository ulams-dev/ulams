<?php

namespace EscolaLms\Reports\Stats\Topic;

use EscolaLms\Courses\Models\Topic;
use EscolaLms\Reports\Stats\StatsContract;

abstract class AbstractTopicStat implements StatsContract
{
    protected Topic $topic;

    public function __construct(Topic $topic)
    {
        $this->topic = $topic;
    }

    public static function make(Topic $topic)
    {
        return new static($topic);
    }

    public static function requiredPackagesInstalled(): bool
    {
        return class_exists(Topic::class);
    }
}
