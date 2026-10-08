<?php

namespace Ulams\Courses\Tests\Models\TopicContent;

use Ulams\Courses\Models\TopicContent\AbstractTopicContent;
use Ulams\Courses\Tests\Database\Factories\ExampleTopicTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExampleTopicType extends AbstractTopicContent
{
    use HasFactory;

    public $table = 'topic_example';

    protected static function newFactory()
    {
        return ExampleTopicTypeFactory::new();
    }

    public function getMorphClass()
    {
        return self::class;
    }
}
