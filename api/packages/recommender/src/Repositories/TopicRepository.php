<?php

namespace EscolaLms\Recommender\Repositories;

use EscolaLms\Core\Repositories\BaseRepository;
use EscolaLms\Recommender\Models\Topic;
use EscolaLms\Recommender\Repositories\Contracts\TopicRepositoryContract;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;

class TopicRepository extends BaseRepository implements TopicRepositoryContract
{
    public function getFieldsSearchable(): array
    {
        return [];
    }

    public function model(): string
    {
        return Topic::class;
    }


    public function countTopicByCourseId(int $courseId): int
    {
        return $this->model
            ->newQuery()
            ->whereHas('lesson', fn(Builder $query) => $query
                ->whereHas('course', fn(Builder $query) => $query
                    ->where('id', $courseId)
                )
            )
            ->count();
    }
    public function getAllByCourseId(int $courseId, ?string $orderDir = 'desc'): Collection
    {
        return $this->model
            ->newQuery()
            ->whereHas('lesson', fn(Builder $query) => $query
                ->whereHas('course', fn(Builder $query) => $query
                    ->where('id', $courseId)
                )
            )
            ->orderBy('order', $orderDir)
            ->get();
    }

    public function getAllByLessonId(int $lessonId, ?string $orderDir = 'desc', ?int $limit = null): Collection
    {
        return $this->model
            ->newQuery()
            ->whereHas('lesson', fn(Builder $query) => $query->where('id', $lessonId))
            ->when(isset($limit), fn(Builder $query) => $query->limit($limit))
            ->orderBy('order', $orderDir)
            ->get();
    }
}
