<?php

namespace Ulams\Courses\Repositories\Contracts;

use Ulams\Core\Dtos\OrderDto;
use Ulams\Core\Repositories\Contracts\BaseRepositoryContract;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

interface CourseRepositoryContract extends BaseRepositoryContract
{
    public function allQueryBuilder(array $search = [], array $criteria = []): Builder;

    public function queryAll(): Builder;

    public function findTutors(): Collection;
    public function findTutor($id): ?User;

    public function getById(int $id): Course;

    public function deleteModel(Course $course): ?bool;

    public function syncAuthors(Course $course, array $authors = []): void;
    public function addAuthor(Course $course, User $author): void;
    public function removeAuthor(Course $course, User $author): void;

    public function getAuthoredCourses(int $id, OrderDto $orderDto): Builder;
}
