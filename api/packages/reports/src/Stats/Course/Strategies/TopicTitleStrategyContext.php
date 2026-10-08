<?php

namespace EscolaLms\Reports\Stats\Course\Strategies;

use EscolaLms\Courses\Models\Topic;

class TopicTitleStrategyContext
{
    private TopicTitleStrategy $strategy;

    private Topic $topic;

    public function __construct(Topic $topic)
    {
        $this->topic = $topic;
        $this->resolve();
    }

    private function resolve(): void
    {
        if (class_exists(\EscolaLms\TopicTypes\Models\TopicContent\H5P::class) && $this->topic->topicable_type === \EscolaLms\TopicTypes\Models\TopicContent\H5P::class) {
            $this->strategy = new H5PTopicTitleStrategy($this->topic);
            return;
        }

        $this->strategy = new DefaultTopicTitleStrategy($this->topic);
    }

    public function getStrategy(): TopicTitleStrategy
    {
        return $this->strategy;
    }
}
