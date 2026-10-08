<?php

namespace EscolaLms\Settings\Services;

use EscolaLms\Settings\Http\Resources\SettingResource;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use EscolaLms\Settings\Services\Contracts\SettingsServiceContract;
use EscolaLms\Settings\Models\Setting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SettingsService implements SettingsServiceContract
{
    public function publicList(): Collection
    {
        return Setting::where([
            ['public', true],
            ['enumerable', true]
        ])->orderBy('sort')->get();
    }

    public function find(string $group, string $key, $public = null): Model
    {
        $where = [
            ['group', $group],
            ['key', $key]
        ];
        if (isset($public)) {
            $where[] = ['public', $public];
        }
        return Setting::where($where)->firstOrFail();
    }

    public function searchAndPaginate(array $search = [], ?int $per_page = 15): LengthAwarePaginator|Collection
    {
        /** @var Builder $query */
        $query = Setting::query();

        if (Arr::get($search, 'group')) {
            $query->where('group', '=', $search['group']);
        }
        if (Arr::get($search, 'key')) {
            $query->where('key', '=', $search['key']);
        }

        return $per_page <= 0 ? $query->get() : $query->paginate($per_page);
    }

    public function groups(): Collection
    {
        return DB::table('settings')->select('group')->distinct()->pluck('group');
    }
}
