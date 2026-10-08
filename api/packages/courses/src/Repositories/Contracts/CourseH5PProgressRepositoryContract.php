<?php

namespace Ulams\Courses\Repositories\Contracts;

use Ulams\Core\Repositories\Contracts\BaseRepositoryContract;
use Ulams\Courses\Models\H5PUserProgress;
use Ulams\Courses\Models\Topic;
use Illuminate\Contracts\Auth\Authenticatable;

interface CourseH5PProgressRepositoryContract extends BaseRepositoryContract
{
    public function store(Topic $topic, Authenticatable $user, string $event, $data): H5PUserProgress;
}
