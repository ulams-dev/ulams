<?php

namespace EscolaLms\Recommender\Repositories\Contracts;

use EscolaLms\Core\Repositories\Contracts\BaseRepositoryContract;
use Illuminate\Support\Collection;

interface TopicRepositoryContract extends BaseRepositoryContract
{
    public function countTopicByCourseId(int $courseId): int;

    public function getAllByCourseId(int $courseId, ?string $orderDir = 'desc'): Collection;

    public function getAllByLessonId(int $lessonId, ?string $orderDir = 'desc', ?int $limit = null): Collection;
}
