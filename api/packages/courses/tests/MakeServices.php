<?php

namespace Ulams\Courses\Tests;

use Ulams\Categories\Services\Contracts\CategoryServiceContracts;
use Ulams\Core\Repositories\Contracts\ConfigRepositoryContract;
use Ulams\Courses\Repositories\Contracts\CourseProgressRepositoryContract;
use Ulams\Courses\Services\Contracts\CourseServiceContract;

trait MakeServices
{
    public function courseService(): CourseServiceContract
    {
        return $this->courseService = app(CourseServiceContract::class);
    }

    public function courseProgressRepository(): CourseProgressRepositoryContract
    {
        return $this->courseProgressRepository = app(CourseProgressRepositoryContract::class);
    }

    public function categoryService(): CategoryServiceContracts
    {
        return $this->courseService = app(CategoryServiceContracts::class);
    }

    public function configRepository(): ConfigRepositoryContract
    {
        return $this->configRepository = app(ConfigRepositoryContract::class);
    }
}
