<?php

namespace Ulams\TopicTypeGift\Repositories;

use Ulams\Core\Repositories\BaseRepository;
use Ulams\TopicTypeGift\Models\AttemptAnswer;
use Ulams\TopicTypeGift\Repositories\Contracts\AttemptAnswerRepositoryContract;

class AttemptAnswerRepository extends BaseRepository implements AttemptAnswerRepositoryContract
{
    public function model(): string
    {
        return AttemptAnswer::class;
    }

    public function getFieldsSearchable(): array
    {
        return [];
    }

    public function updateOrCreate(array $attributes = [], array $values = []): AttemptAnswer
    {
        /** @var AttemptAnswer */
        return $this->model->newQuery()->updateOrCreate($attributes, $values);
    }
}
