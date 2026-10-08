<?php

namespace EscolaLms\TopicTypeGift\Repositories\Contracts;

use EscolaLms\Core\Repositories\Contracts\BaseRepositoryContract;
use Illuminate\Database\Eloquent\Collection;

interface GiftQuizRepositoryContract extends BaseRepositoryContract
{
    public function getByCourseId(int $courseId): Collection;
}
