<?php

namespace Ulams\TopicTypeGift\Services\Contracts;

use Ulams\TopicTypeGift\Dtos\AdminSortQuestionDto;
use Ulams\TopicTypeGift\Dtos\GiftQuestionDto;
use Ulams\TopicTypeGift\Exceptions\UnknownGiftTypeException;
use Ulams\TopicTypeGift\Models\GiftQuestion;

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
