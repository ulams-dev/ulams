<?php

namespace Tests;

use App\Repositories\Contracts\CourseProgressRepositoryContract;
use Ulams\Categories\Services\Contracts\CategoryServiceContracts;
use App\Services\Ulams\Contracts\CourseServiceContract;

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
}
