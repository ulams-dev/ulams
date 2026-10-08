<?php

namespace EscolaLms\BulkNotifications\Repositories;

use EscolaLms\BulkNotifications\Models\User;
use EscolaLms\BulkNotifications\Repositories\Contracts\UserRepositoryContract;
use EscolaLms\Core\Repositories\BaseRepository;
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
