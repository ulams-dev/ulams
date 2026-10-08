<?php

namespace EscolaLms\TopicTypeGift\Dtos;

use EscolaLms\Core\Dtos\Contracts\DtoContract;
use EscolaLms\Core\Dtos\Contracts\InstantiateFromRequest;
use Illuminate\Http\Request;

class AdminUpdateAttemptAnswerDto implements DtoContract, InstantiateFromRequest
{
    private float $score;
    private ?string $feedback;

    public function __construct(float $score, ?string $feedback)
    {
        $this->score = $score;
        $this->feedback = $feedback;
    }

    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'feedback' => $this->feedback,
        ];
    }

    public static function instantiateFromRequest(Request $request): self
    {
        return new static(
            $request->input('score'),
            $request->input('feedback'),
        );
    }
}
