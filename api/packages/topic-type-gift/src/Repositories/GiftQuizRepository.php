<?php

namespace EscolaLms\TopicTypeGift\Repositories;

use EscolaLms\Core\Repositories\BaseRepository;
use EscolaLms\TopicTypeGift\Models\GiftQuiz;
use EscolaLms\TopicTypeGift\Repositories\Contracts\GiftQuizRepositoryContract;
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
