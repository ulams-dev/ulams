<?php

namespace Ulams\Courses\Services\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Removes the content row (the "topicable") that belonged to a topic.
 *
 * Topic types register one for their content class when deleting the row needs more than
 * `$content->delete()` (for example when learner data must survive).
 */
interface TopicContentDeleter
{
    public function supports(Model $content): bool;

    public function delete(Model $content): void;
}
