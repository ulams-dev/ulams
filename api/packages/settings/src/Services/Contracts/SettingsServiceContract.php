<?php

namespace EscolaLms\Settings\Services\Contracts;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

interface SettingsServiceContract
{
    public function publicList(): Collection;

    public function find(string $group, string $key, $public = null): Model;

    public function searchAndPaginate(array $search = [], ?int $per_page  = 15): LengthAwarePaginator|Collection;

    public function groups(): Collection;
}
