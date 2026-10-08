<?php

namespace EscolaLms\StationaryEvents\Repositories;

use EscolaLms\Core\Repositories\BaseRepository;
use EscolaLms\StationaryEvents\Models\StationaryEvent;
use EscolaLms\StationaryEvents\Models\StationaryEventAuthorPivot;
use EscolaLms\StationaryEvents\Models\StationaryEventUserPivot;
use EscolaLms\StationaryEvents\Repositories\Contracts\StationaryEventRepositoryContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class StationaryEventRepository extends BaseRepository implements StationaryEventRepositoryContract
{
    protected $fieldSearchable = [
        'name',
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return StationaryEvent::class;
    }

    public function allQueryBuilder(array $criteria = []): Builder
    {
        $query = $this->allQuery();

        if (!empty($criteria)) {
            $query = $this->applyCriteria($query, $criteria);
        }

        return $query;
    }

    public function forCurrentUser(array $criteria = []): Builder
    {
        $userId = auth()->user()->getKey();

        return $this->allQueryBuilder($criteria)
            ->whereHas('users', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->orWhereHas('authors', function ($query) use ($userId) {
                $query->where('author_id', $userId);
            })
            ->leftJoin(StationaryEventUserPivot::TABLE_NAME . ' as user_pivot', function ($join) use ($userId) {
                $join->on('user_pivot.stationary_event_id', '=', StationaryEvent::TABLE_NAME . '.id')
                    ->where('user_pivot.user_id', $userId);
            })
            ->leftJoin(StationaryEventAuthorPivot::TABLE_NAME . ' as author_pivot', function ($join) use ($userId) {
                $join->on('author_pivot.stationary_event_id', '=', StationaryEvent::TABLE_NAME . '.id')
                    ->where('author_pivot.author_id', $userId);
            })
            ->select(StationaryEvent::TABLE_NAME . '.*')
            ->selectRaw('GREATEST(COALESCE(user_pivot.created_at, \'1970-01-01 00:00:00\'), COALESCE(author_pivot.created_at, \'1970-01-01 00:00:00\')) as max_created_at')
            ->orderBy('max_created_at', 'desc');

    }
}
