<?php

namespace EscolaLms\TopicTypeGift\Dtos;

use EscolaLms\Core\Dtos\Contracts\DtoContract;
use EscolaLms\Core\Dtos\Contracts\InstantiateFromRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class QuizAttemptDto implements DtoContract, InstantiateFromRequest
{
    private int $quizId;
    private int $userId;
    private Carbon $startedAt;

    public function __construct(int $quizId, int $userId, Carbon $startedAt)
    {
        $this->quizId = $quizId;
        $this->userId = $userId;
        $this->startedAt = $startedAt;
    }

    public function getQuizId(): int
    {
        return $this->quizId;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getStartedAt(): Carbon
    {
        return $this->startedAt;
    }

    public function toArray(): array
    {
        return [
            'topic_gift_quiz_id' => $this->getQuizId(),
            'user_id' => $this->getUserId(),
            'started_at' => $this->getStartedAt(),
        ];
    }

    public static function instantiateFromRequest(Request $request): self
    {
        return new static(
            $request->get('topic_gift_quiz_id'),
            auth()->id(),
            Carbon::now()
        );
    }
}
