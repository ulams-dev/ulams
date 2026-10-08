<?php

namespace Ulams\Tasks\Repositories;

use Ulams\Core\Repositories\BaseRepository;
use Ulams\Tasks\Models\TaskNote;
use Ulams\Tasks\Repositories\Contracts\TaskNoteRepositoryContract;

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
