<?php

namespace EscolaLms\TopicTypeGift\Dtos;

use EscolaLms\Core\Dtos\Contracts\DtoContract;
use EscolaLms\Core\Dtos\Contracts\InstantiateFromRequest;
use Illuminate\Http\Request;

class QuizDto implements DtoContract, InstantiateFromRequest
{

    private string $value;
    private ?int $maxAttempts;
    private ?int $maxExecutionTime;
    private ?float $minPassScore;
    private ?bool $countsToGrade;
    private ?int $weight;
    private ?bool $randomizeOrder;

    public function __construct(string $value, ?int $maxAttempts, ?int $maxExecutionTime, ?float $minPassScore, ?bool $countsToGrade = null, ?int $weight = null, ?bool $randomizeOrder = null)
    {
        $this->value = $value;
        $this->maxAttempts = $maxAttempts;
        $this->maxExecutionTime = $maxExecutionTime;
        $this->minPassScore = $minPassScore;
        $this->countsToGrade = $countsToGrade;
        $this->weight = $weight;
        $this->randomizeOrder = $randomizeOrder;
    }

    public function toArray(): array
    {
        $result = [
            'value' => $this->value,
            'max_attempts' => $this->maxAttempts,
            'max_execution_time' => $this->maxExecutionTime,
            'min_pass_score' => $this->minPassScore,
        ];

        if (!is_null($this->countsToGrade)) {
            $result['counts_to_grade'] = $this->countsToGrade;
        }

        if (!is_null($this->weight)) {
            $result['weight'] = $this->weight;
        }

        if (!is_null($this->randomizeOrder)) {
            $result['randomize_order'] = $this->randomizeOrder;
        }

        return $result;
    }

    public static function instantiateFromRequest(Request $request): QuizDto
    {
        return new self(
            $request->input('value'),
            $request->input('max_attempts'),
            $request->input('max_execution_time'),
            $request->input('min_pass_score'),
            $request->has('counts_to_grade') ? $request->boolean('counts_to_grade') : null,
            $request->has('weight') ? $request->integer('weight') : null,
            $request->has('randomize_order') ? $request->boolean('randomize_order') : null
        );
    }
}
