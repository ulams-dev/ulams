<?php

namespace Ulams\TopicTypeGift\Repositories;

use Ulams\Core\Repositories\BaseRepository;
use Ulams\TopicTypeGift\Models\GiftQuiz;
use Ulams\TopicTypeGift\Repositories\Contracts\GiftQuizRepositoryContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class GiftQuizRepository extends BaseRepository implements GiftQuizRepositoryContract
{
    public function model(): string
    {
        return GiftQuiz::class;
    }

    public function getFieldsSearchable(): array
    {
        return [];
    }

    public function getByCourseId(int $courseId): Collection
    {
        return $this->allQuery()
            ->whereHas('topic.lesson', fn (Builder $query) => $query->where('course_id', $courseId))
            ->with('topic')
            ->get();
    }
}
