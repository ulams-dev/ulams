<?php

namespace EscolaLms\TopicTypeGift\Dtos;

use EscolaLms\Core\Dtos\Contracts\DtoContract;
use EscolaLms\Core\Dtos\Contracts\InstantiateFromRequest;
use Illuminate\Http\Request;

class GiftQuestionDto implements DtoContract, InstantiateFromRequest
{
    private int $giftQuizId;
    private string $value;
    private int $score;
    private ?int $order;
    private ?int $categoryId;

    public function __construct(int $giftQuizId, string $value, int $score, ?int $order, ?int $categoryId)
    {
        $this->giftQuizId = $giftQuizId;
        $this->value = $value;
        $this->score = $score;
        $this->order = $order;
        $this->categoryId = $categoryId;
    }

    public function getGiftQuizId(): int
    {
        return $this->giftQuizId;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function getOrder(): ?int
    {
        return $this->order;
    }

    public function getCategoryId(): ?int
    {
        return $this->categoryId;
    }

    public function toArray(): array
    {
        $result = [
            'topic_gift_quiz_id' => $this->getGiftQuizId(),
            'value' => $this->getValue(),
            'score' => $this->getScore(),
            'category_id' => $this->getCategoryId(),
        ];

        if ($this->getOrder()) {
            $result['order'] = $this->getOrder();
        }

        return $result;
    }

    public static function instantiateFromRequest(Request $request): self
    {
        return new static(
            $request->input('topic_gift_quiz_id'),
            $request->input('value'),
            $request->input('score'),
            $request->input('order'),
            $request->input('category_id')
        );
    }
}
