<?php

namespace Ulams\Reports\Stats\Course\Strategies;

use Ulams\Courses\Models\Topic;
use Ulams\H5P\Models\H5PContent;

class H5PTopicTitleStrategy implements TopicTitleStrategy
{
    private Topic $topic;

    public function __construct(Topic $topic)
    {
        $this->topic = $topic;
    }

    public function makeTitle(): string
    {
        $h5pContent = H5PContent::query()->find($this->topic->topicable->value);

        if (!$h5pContent || !$h5pContent->main_library) {
            return class_basename($this->topic->topicable_type) . ' # ' . ($this->topic->topic_title ?? $this->topic->title);
        }

        // "H5P.MultiChoice 1.16 # Topic title"
        return $h5pContent->library . ' # ' . ($this->topic->topic_title ?? $this->topic->title);
    }
}
