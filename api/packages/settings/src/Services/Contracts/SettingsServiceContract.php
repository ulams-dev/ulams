<?php

namespace Ulams\Settings\Services\Contracts;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

interface SettingsServiceContract
{
    public function publicList(): Collection;

    public function find(string $group, string $key, $public = null): Model;

    public function searchAndPaginate(array $search = [], ?int $per_page  = 15): LengthAwarePaginator|Collection;

    public function groups(): Collection;

    /** Creates or updates one setting value (the theme step of the course builder uses it). */
    public function put(string $group, string $key, string $value, string $type = 'text', bool $public = true): Model;
}
