<?php

namespace Ulams\LearnerInsights\Services;

use Ulams\CourseBuilder\Services\ElementLookup;
use Ulams\Courses\Models\Topic;

/**
 * Maps LMS entities to the element a signal is about: the blueprint element id when the course was
 * built with the Course Builder, otherwise `topic:<id>` or `question:<id>` (ADR 0057).
 */
class ElementResolver
{
    public function __construct(private readonly ElementLookup $lookup)
    {
    }

    /**
     * @return array{course_id:?int,topic_id:?int,element_key:?string,element_id:?string,session_id:?string}
     */
    public function resolve(?int $courseId, ?int $topicId, ?int $questionId = null): array
    {
        if ($topicId !== null && $courseId === null) {
            $courseId = $this->courseOfTopic($topicId);
        }

        $mapped = ($questionId !== null ? $this->lookup->forGiftQuestion($questionId) : null)
            ?? ($topicId !== null ? $this->lookup->forTopic($topicId) : null);

        if ($mapped) {
            return [
                'course_id' => $courseId, 'topic_id' => $topicId,
                'element_key' => $mapped['element_id'], 'element_id' => $mapped['element_id'], 'session_id' => $mapped['session_id'],
            ];
        }

        $key = $questionId !== null ? 'question:' . $questionId : ($topicId !== null ? 'topic:' . $topicId : null);

        return ['course_id' => $courseId, 'topic_id' => $topicId, 'element_key' => $key, 'element_id' => null, 'session_id' => null];
    }

    public function courseOfTopic(int $topicId): ?int
    {
        $topic = Topic::query()->with('lesson')->find($topicId);

        return $topic?->course?->getKey();
    }
}
