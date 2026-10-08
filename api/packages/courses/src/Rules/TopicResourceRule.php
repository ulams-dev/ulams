<?php

namespace Ulams\Courses\Rules;

use Ulams\Courses\Models\Topic;
use Ulams\Files\Rules\FileOrStringRule;

class TopicResourceRule extends FileOrStringRule
{
    public function __construct(?array $fileRules = [], ?int $topicId = null)
    {
        if (is_null($topicId)) {
            return false;
        }
        $topic = Topic::findOrFail($topicId);
        $prefixPath = 'course/' . $topic->course->getKey();

        parent::__construct(empty($fileRules) ? ['file'] : $fileRules, $prefixPath);
    }
}
