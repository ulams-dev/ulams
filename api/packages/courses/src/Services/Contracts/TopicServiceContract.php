<?php

namespace Ulams\Courses\Services\Contracts;

use Ulams\Courses\Models\Topic;
use Illuminate\Database\Eloquent\Model;

interface TopicServiceContract
{
    public function cloneTopic(Topic $topic): Model;
}
