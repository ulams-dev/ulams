<?php

namespace Ulams\TopicTypeGift\Repositories;

use Ulams\Core\Repositories\BaseRepository;
use Ulams\TopicTypeGift\Models\GiftQuestion;
use Ulams\TopicTypeGift\Repositories\Contracts\GiftQuestionRepositoryContract;

class GiftQuestionRepository extends BaseRepository implements GiftQuestionRepositoryContract
{
    public function model(): string
    {
        return GiftQuestion::class;
    }

    public function getFieldsSearchable(): array
    {
        return [];
    }
}
