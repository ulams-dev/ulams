<?php

namespace EscolaLms\Cmi5\Repositories;

use EscolaLms\Cmi5\Models\Cmi5;
use EscolaLms\Cmi5\Models\Cmi5Au;
use EscolaLms\Cmi5\Repositories\Contracts\Cmi5RepositoryContract;
use EscolaLms\Core\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class Cmi5Repository extends BaseRepository implements Cmi5RepositoryContract
{
    public function getFieldsSearchable(): array
    {
        return [];
    }

    public function model(): string
    {
        return Cmi5::class;
    }

    /**
     * @throws Throwable
     */
    public function save(Cmi5 $cmi5, Collection $cmi5Aus): Cmi5
    {
        DB::transaction(function () use($cmi5, $cmi5Aus) {
            $cmi5->save();
            $cmi5Aus->each(function (Cmi5Au $cmi5Au) use ($cmi5) {
                $cmi5Au->cmi5_id = $cmi5->getKey();
                $cmi5Au->save();
            });
        });

        return $cmi5;
    }

    public function paginate(int $perPage, array $columns = ['*']): LengthAwarePaginator
    {
        return $this->model
            ->newQuery()
            ->with('aus')
            ->paginate($perPage, $columns);
    }
}
