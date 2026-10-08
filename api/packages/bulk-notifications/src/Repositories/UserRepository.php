<?php

namespace Ulams\BulkNotifications\Repositories;

use Ulams\BulkNotifications\Models\User;
use Ulams\BulkNotifications\Repositories\Contracts\UserRepositoryContract;
use Ulams\Core\Repositories\BaseRepository;
use Illuminate\Support\Collection;

class UserRepository extends BaseRepository implements UserRepositoryContract
{
    public function getFieldsSearchable(): array
    {
        return [];
    }

    public function model(): string
    {
        return User::class;
    }

    public function findAllIds(): Collection
    {
        return $this->model->newQuery()->select('id')->get();
    }
}
