<?php

namespace Ulams\Courses\Observers;

use Ulams\Courses\Models\Topic;
use Spatie\ResponseCache\Facades\ResponseCache;

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
        ResponseCache::clear();
    }

    public function deleted(Topic $topic)
    {
        ResponseCache::clear();
    }
}
