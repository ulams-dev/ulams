<?php

namespace EscolaLms\Recommender\Dto;

use EscolaLms\Core\Dtos\Contracts\DtoContract;

class PredictSatisfactionDto implements DtoContract
{
    protected string $model_type;
    protected int $model_id;
    protected string $term;
    protected string $start_at;
    protected string $end_at;
    protected string $api_url;
    protected int $term_analytic_id;

    /**
     * Satisfaction model IDs to score (one or many).
     * Frames sends them to Makaruk as `model` (one) or `models` (many).
     *
     * @var string[]
     */
    protected array $satisfaction_models;

    /**
     * @param string[] $satisfaction_models
     */
    public function __construct(string $model_type, int $model_id, string $term, string $start_at, string $end_at, string $api_url, int $term_analytic_id, array $satisfaction_models = [])
    {
        $this->model_type = $model_type;
        $this->model_id = $model_id;
        $this->term = $term;
        $this->start_at = $start_at;
        $this->end_at = $end_at;
        $this->api_url = $api_url;
        $this->term_analytic_id = $term_analytic_id;
        $this->satisfaction_models = $satisfaction_models;
    }

    public function getModelType(): string
    {
        return $this->model_type;
    }

    public function getModelId(): int
    {
        return $this->model_id;
    }

    public function getTerm(): string
    {
        return $this->term;
    }

    public function getStartAt(): string
    {
        return $this->start_at;
    }

    public function getEndAt(): string
    {
        return $this->end_at;
    }

    public function getApiUrl(): string
    {
        return $this->api_url;
    }

    public function getTermAnalyticId(): int
    {
        return $this->term_analytic_id;
    }

    /**
     * @return string[]
     */
    public function getSatisfactionModels(): array
    {
        return $this->satisfaction_models;
    }

    public function toArray(): array
    {
        return [
            'model_type' => $this->model_type,
            'model_id' => $this->model_id,
            'term' => $this->term,
            'start_at' => $this->start_at,
            'end_at' => $this->end_at,
            'api_url' => $this->api_url,
            'term_analytic_id' => $this->term_analytic_id,
            'satisfaction_models' => $this->satisfaction_models,
        ];
    }

    public static function instantiateFromArray(array $data): self
    {
        return new self(
            $data['model_type'],
            $data['model_id'],
            $data['term'],
            $data['start_at'],
            $data['end_at'],
            $data['api_url'],
            $data['term_analytic_id'],
            $data['satisfaction_models'] ?? []
        );
    }
}
