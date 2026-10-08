<?php

namespace EscolaLms\Recommender\Dto;

use EscolaLms\Core\Dtos\Contracts\DtoContract;
use EscolaLms\Core\Dtos\Contracts\InstantiateFromRequest;
use Illuminate\Http\Request;

class SatisfactionDto implements DtoContract, InstantiateFromRequest
{
    protected int $term_analytic_id;
    protected string $mean_predicted_rating;

    /**
     * Per-model ratings (one or many selected models).
     *
     * @var array<int, array{model: ?string, model_label: ?string, model_version: ?string, mean_predicted_rating: mixed}>
     */
    protected array $satisfaction_models;

    /**
     * @param array<int, array> $satisfaction_models
     */
    public function __construct(int $term_analytic_id, string $mean_predicted_rating, array $satisfaction_models = [])
    {
        $this->term_analytic_id = $term_analytic_id;
        $this->mean_predicted_rating = $mean_predicted_rating;
        $this->satisfaction_models = $satisfaction_models;
    }

    public function toArray(): array
    {
        return [
            'term_analytic_id' => $this->term_analytic_id,
            'mean_predicted_rating' => $this->mean_predicted_rating,
            'satisfaction_models' => $this->satisfaction_models,
        ];
    }

    public static function instantiateFromRequest(Request $request): self
    {
        return new self(
            $request->get('term_analytic_id'),
            $request->get('mean_predicted_rating'),
            $request->get('satisfaction_models', []),
        );
    }

    public function getTermAnalyticId(): int
    {
        return $this->term_analytic_id;
    }

    public function getMeanPredictedRating(): string
    {
        return $this->mean_predicted_rating;
    }

    /**
     * @return array<int, array>
     */
    public function getSatisfactionModels(): array
    {
        return $this->satisfaction_models;
    }
}
