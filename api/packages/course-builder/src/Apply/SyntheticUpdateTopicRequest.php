<?php

namespace Ulams\CourseBuilder\Apply;

use Ulams\Courses\Http\Requests\UpdateTopicAPIRequest;
use Ulams\Courses\Models\Topic;

/**
 * `TopicRepositoryContract::updateFromRequest` reads the topic from the route; the applier has no
 * route, so this request carries the topic directly. Validation still uses the courses rules.
 */
final class SyntheticUpdateTopicRequest extends UpdateTopicAPIRequest
{
    public ?Topic $topicModel = null;

    public function getTopic(): ?Topic
    {
        return $this->topicModel;
    }
}
