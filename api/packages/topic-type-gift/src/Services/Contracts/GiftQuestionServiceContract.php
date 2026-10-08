<?php

namespace EscolaLms\TopicTypeGift\Services\Contracts;

use EscolaLms\TopicTypeGift\Dtos\AdminSortQuestionDto;
use EscolaLms\TopicTypeGift\Dtos\GiftQuestionDto;
use EscolaLms\TopicTypeGift\Exceptions\UnknownGiftTypeException;
use EscolaLms\TopicTypeGift\Models\GiftQuestion;

interface GiftQuestionServiceContract
{
    public function create(GiftQuestionDto $dto): GiftQuestion;
    public function update(GiftQuestionDto $dto, int $id): GiftQuestion;
    public function delete(int $id): void;

    /**
     * @throws UnknownGiftTypeException
     */
    public function getType(string $question): string;
    public function getAnswerFromQuestion(string $question): string;
    public function removeComment(string $question): string;
    public function sort(AdminSortQuestionDto $dto): void;
}
