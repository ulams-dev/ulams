<?php

namespace EscolaLms\TopicTypeGift\Dtos;

use EscolaLms\Core\Dtos\Contracts\DtoContract;
use EscolaLms\Core\Dtos\Contracts\InstantiateFromRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SaveAllAttemptAnswersDto implements DtoContract, InstantiateFromRequest
{
    private int $attemptId;
    private array $answers;

    public function __construct(int $attemptId, array $answers)
    {
        $this->attemptId = $attemptId;
        $this->answers = $answers;
    }

    public function toArray(): array
    {
        return [];
    }

    public function getAttemptId(): int
    {
        return $this->attemptId;
    }

    public function getAnswers(): array
    {
        return $this->answers;
    }

    public static function instantiateFromRequest(Request $request): self
    {
        return new static(
            $request->input('topic_gift_quiz_attempt_id'),
            $request->input('answers', []),
        );
    }
}
