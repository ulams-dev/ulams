<?php

namespace EscolaLms\TopicTypeProject\Dtos;

use EscolaLms\Core\Dtos\Contracts\DtoContract;
use EscolaLms\Core\Dtos\Contracts\InstantiateFromRequest;
use Illuminate\Http\Request;

class GradeProjectSolutionDto implements DtoContract, InstantiateFromRequest
{
    protected float $score;
    protected int $gradedBy;

    public function __construct(float $score, int $gradedBy)
    {
        $this->score = $score;
        $this->gradedBy = $gradedBy;
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function getGradedBy(): int
    {
        return $this->gradedBy;
    }

    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'graded_by' => $this->gradedBy,
        ];
    }

    public static function instantiateFromRequest(Request $request): self
    {
        return new static(
            (float) $request->input('score'),
            (int) auth()->id()
        );
    }
}
