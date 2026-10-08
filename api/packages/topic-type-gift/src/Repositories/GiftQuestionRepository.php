<?php

namespace EscolaLms\TopicTypeGift\Repositories;

use EscolaLms\Core\Repositories\BaseRepository;
use EscolaLms\TopicTypeGift\Models\GiftQuestion;
use EscolaLms\TopicTypeGift\Repositories\Contracts\GiftQuestionRepositoryContract;

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
