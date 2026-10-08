<?php

namespace EscolaLms\Reports\Stats\Course\Strategies;

use EscolaLms\Courses\Models\Topic;

class DefaultTopicTitleStrategy implements TopicTitleStrategy
{
    private Topic $topic;

    public function __construct(Topic $topic)
    {
        $this->topic = $topic;
    }

    public function makeTitle(): string
    {
        return class_basename($this->topic->topicable_type) . ' # ' . ($this->topic->topic_title ?? $this->topic->title);
    }
}
