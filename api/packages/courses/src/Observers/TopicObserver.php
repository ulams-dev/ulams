<?php

namespace Ulams\Courses\Observers;

use Ulams\Courses\Models\Topic;
use Ulams\Courses\Support\ResponseCacheTags;

class TopicObserver
{
    public function creating(Topic $topic)
    {
        if ($topic->lesson_id && !$topic->order) {
            $topic->order = 1 + (int) Topic::where('lesson_id', $topic->lesson_id)->max('order');
        }
    }

    public function saved(Topic $topic)
    {
        ResponseCacheTags::clear(ResponseCacheTags::CATALOGUE);
    }

    public function deleted(Topic $topic)
    {
        ResponseCacheTags::clear(ResponseCacheTags::CATALOGUE);
    }
}
