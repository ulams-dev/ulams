<?php

namespace EscolaLms\TopicTypeGift\Services;

use EscolaLms\TopicTypeGift\Dtos\AdminSortQuestionDto;
use EscolaLms\TopicTypeGift\Dtos\GiftQuestionDto;
use EscolaLms\TopicTypeGift\Enum\QuestionTypeEnum;
use EscolaLms\TopicTypeGift\Exceptions\UnknownGiftTypeException;
use EscolaLms\TopicTypeGift\Models\GiftQuestion;
use EscolaLms\TopicTypeGift\Repositories\Contracts\GiftQuestionRepositoryContract;
use EscolaLms\TopicTypeGift\Services\Contracts\GiftQuestionServiceContract;
use Illuminate\Support\Str;

class GiftQuestionService implements GiftQuestionServiceContract
{
    private GiftQuestionRepositoryContract $giftQuestionRepository;

    public function __construct(GiftQuestionRepositoryContract $giftQuestionRepository)
    {
        $this->giftQuestionRepository = $giftQuestionRepository;
    }

    /**
     * @throws UnknownGiftTypeException
     */
    public function create(GiftQuestionDto $dto): GiftQuestion
    {
        /** @var GiftQuestion */
        return $this->giftQuestionRepository->create(array_merge($dto->toArray(), [
                'type' => $this->getType($dto->getValue()),
            ])
        );
    }

    /**
     * @throws UnknownGiftTypeException
     */
    public function update(GiftQuestionDto $dto, int $id): GiftQuestion
    {
        /** @var GiftQuestion */
        return $this->giftQuestionRepository->update(array_merge($dto->toArray(), [
            'type' => $this->getType($dto->getValue()),
        ]), $id);
    }

    public function delete(int $id): void
    {
        $this->giftQuestionRepository->delete($id);
    }

    /**
     * @throws UnknownGiftTypeException
     */
    public function getType(string $question): string
    {
        if (!Str::containsAll($question, ['{', '}'])) {
            return QuestionTypeEnum::DESCRIPTION;
        }

        $answer = $this->getAnswerFromQuestion($question);

        if (!$answer) {
            return QuestionTypeEnum::ESSAY;
        }

        if (Str::startsWith($answer, '#')) {
            return QuestionTypeEnum::NUMERICAL_QUESTION;
        }

        if (Str::contains($answer, '~%')) {
            return QuestionTypeEnum::MULTIPLE_CHOICE_WITH_MULTIPLE_RIGHT_ANSWERS;
        }

        if (Str::containsAll($answer, ['~', '='])) {
            return QuestionTypeEnum::MULTIPLE_CHOICE;
        }

        if (Str::containsAll($answer, ['=', '->'])) {
            return QuestionTypeEnum::MATCHING;
        }

        if (Str::contains($answer, '=') && !Str::contains($answer, '~')) {
            return QuestionTypeEnum::SHORT_ANSWERS;
        }

        if (Str::contains($answer, ['T', 'F', 'TRUE', 'FALSE'])) {
            return QuestionTypeEnum::TRUE_FALSE;
        }

        throw new UnknownGiftTypeException();
    }

    public function getAnswerFromQuestion(string $question): string
    {
        $question = $this->removeComment($question);
        return Str::of(Str::between($question, '{', '}'))->trim();
    }

    public function removeComment(string $question): string
    {
       return preg_replace('/\/\/.*\n/', '', $question);
    }

    public function sort(AdminSortQuestionDto $dto): void
    {
        foreach ($dto->getOrders() as $oder) {
            $this->giftQuestionRepository->update(['order' => $oder['order']], $oder['id']);
        }
    }
}
