<?php

namespace EscolaLms\BulkNotifications\Repositories\Contracts;

use Illuminate\Support\Collection;

interface UserRepositoryContract
{
    public function findAllIds(): Collection;
}
