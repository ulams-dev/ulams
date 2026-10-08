<?php

namespace EscolaLms\Categories\Dtos\Contracts;

use Illuminate\Database\Eloquent\Model;

interface ModelDtoContract
{
    public function model(): Model;
    public function toArray($filters = false): array;
}