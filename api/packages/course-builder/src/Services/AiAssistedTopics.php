<?php

namespace Ulams\CourseBuilder\Services;

use Illuminate\Support\Collection;
use Ulams\CourseBuilder\Models\EntityMapEntry;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use WeakMap;

/**
 * Which topics of a lesson were applied from a builder session (live entries of the entity map).
 * Read-only, like `ElementLookup`. One query per lesson, remembered on the lesson object itself so
 * nothing outlives the request that built it.
 */
class AiAssistedTopics
{
    /** @var WeakMap<Lesson,array<int,true>> */
    private WeakMap $byLesson;

    public function __construct()
    {
        $this->byLesson = new WeakMap();
    }

    public function is(Topic $topic): bool
    {
        $lesson = $topic->lesson;
        if ($lesson === null) {
            return $this->mapped(collect([(int) $topic->getKey()]))->has((int) $topic->getKey());
        }
        if (!isset($this->byLesson[$lesson])) {
            $ids = Topic::query()->where('lesson_id', $lesson->getKey())->pluck('id')->map(fn ($id) => (int) $id);
            $this->byLesson[$lesson] = $this->mapped($ids)->all();
        }

        return isset($this->byLesson[$lesson][(int) $topic->getKey()]);
    }

    /**
     * @param Collection<int,int> $topicIds
     * @return Collection<int,true> keyed by topic id
     */
    private function mapped(Collection $topicIds): Collection
    {
        if ($topicIds->isEmpty()) {
            return collect();
        }

        return EntityMapEntry::query()
            ->whereIn('entity_type', ['topic', 'quiz_topic'])
            ->whereIn('entity_id', $topicIds->all())
            ->whereNull('retired_at')
            ->pluck('entity_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true]);
    }
}
