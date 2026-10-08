<?php

namespace Ulams\TopicTypeGift\Repositories\Contracts;

use Ulams\Core\Repositories\Contracts\BaseRepositoryContract;
use Ulams\TopicTypeGift\Models\AttemptAnswer;

interface AttemptAnswerRepositoryContract extends BaseRepositoryContract
{
    public function updateOrCreate(array $attributes = [], array $values = []): AttemptAnswer;
}
