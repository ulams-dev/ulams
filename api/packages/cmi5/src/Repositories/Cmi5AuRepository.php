<?php

namespace EscolaLms\Cmi5\Repositories;

use EscolaLms\Cmi5\Models\Cmi5Au;
use EscolaLms\Cmi5\Repositories\Contracts\Cmi5AuRepositoryContract;
use EscolaLms\Core\Repositories\BaseRepository;

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
