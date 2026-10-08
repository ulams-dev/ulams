<?php

namespace EscolaLms\Translations\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;

interface LanguageLineRepositoryContract
{
    public function allQueryBuilder(array $search = [], array $criteria = []): Builder;
}