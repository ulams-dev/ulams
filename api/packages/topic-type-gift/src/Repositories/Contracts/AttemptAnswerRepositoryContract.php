<?php

namespace EscolaLms\TopicTypeGift\Repositories\Contracts;

use EscolaLms\Core\Repositories\Contracts\BaseRepositoryContract;
use EscolaLms\TopicTypeGift\Models\AttemptAnswer;

interface AttemptAnswerRepositoryContract extends BaseRepositoryContract
{
    public function updateOrCreate(array $attributes = [], array $values = []): AttemptAnswer;
}
