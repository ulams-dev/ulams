<?php

namespace EscolaLms\Video\Repositories;

use EscolaLms\Core\Repositories\BaseRepository;
use EscolaLms\Video\Models\Video;
use EscolaLms\Video\Repositories\Contracts\VideoRepositoryContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class VideoRepository extends BaseRepository implements VideoRepositoryContract
{
    public function getFieldsSearchable(): array
    {
        return [];
    }

    public function model(): string
    {
        return Video::class;
    }

    public function getByProcessDateBefore(Carbon $dateTime, string $state): Collection
    {
        return $this->model
            ->newQuery()
            ->with('topic')
            ->whereHas('topic', fn (Builder $query) => $query
                ->where('json->ffmpeg->date_time', '<=', $dateTime->toISOString())
                ->where('json->ffmpeg->state', $state)
            )
            ->get();
    }
}
