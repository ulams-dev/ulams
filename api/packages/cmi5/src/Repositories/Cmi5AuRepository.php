<?php

namespace Ulams\Cmi5\Repositories;

use Ulams\Cmi5\Models\Cmi5Au;
use Ulams\Cmi5\Repositories\Contracts\Cmi5AuRepositoryContract;
use Ulams\Core\Repositories\BaseRepository;

class Cmi5AuRepository extends BaseRepository implements Cmi5AuRepositoryContract
{
    public function getFieldsSearchable(): array
    {
        return [];
    }

    public function model(): string
    {
        return Cmi5Au::class;
    }
}
