<?php

namespace EscolaLms\Tasks\Repositories;

use EscolaLms\Core\Repositories\BaseRepository;
use EscolaLms\Tasks\Models\TaskNote;
use EscolaLms\Tasks\Repositories\Contracts\TaskNoteRepositoryContract;

class TaskNoteRepository extends BaseRepository implements TaskNoteRepositoryContract
{
    public function model(): string
    {
        return TaskNote::class;
    }

    public function getFieldsSearchable(): array
    {
        return [];
    }
}
