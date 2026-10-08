<?php

namespace EscolaLms\TopicTypeGift\Dtos;

use EscolaLms\Core\Dtos\Contracts\DtoContract;
use EscolaLms\Core\Dtos\Contracts\InstantiateFromRequest;
use Illuminate\Http\Request;

class SaveAttemptAnswerDto implements DtoContract, InstantiateFromRequest
{
    private int $attemptId;
    private int $questionId;
    private array $answer;

    public function __construct(int $attemptId, int $questionId, array $answer)
    {
        $this->attemptId = $attemptId;
        $this->questionId = $questionId;
        $this->answer = $answer;
    }

    public function getAttemptId(): int
    {
        return $this->attemptId;
    }

    public function getQuestionId(): int
    {
        return $this->questionId;
    }

    public function getAnswer(): array
    {
        return $this->answer;
    }

    public function toArray(): array
    {
        return [
            'topic_gift_quiz_attempt_id' => $this->getAttemptId(),
            'topic_gift_question_id' => $this->getQuestionId(),
        ];
    }

    public static function instantiateFromRequest(Request $request): self
    {
        return new static(
            $request->input('topic_gift_quiz_attempt_id'),
            $request->input('topic_gift_question_id'),
            $request->input('answer', []),
        );
    }
}
