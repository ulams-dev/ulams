<?php

namespace EscolaLms\Cmi5\Parsers\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface Parser
{
    public static function parse(array $data): Model;

    public static function parseCollection(array $data): Collection;
}
