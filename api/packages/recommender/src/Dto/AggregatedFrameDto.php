<?php

namespace EscolaLms\Recommender\Dto;

use EscolaLms\Recommender\Models\AggregatedFrame;

class AggregatedFrameDto extends BaseDto
{
    protected int $externalId;
    protected string $modelType;
    protected int $modelId;
    protected string $term;
    protected string $windowStart;
    protected string $windowEnd;
    protected int $count;
    protected string $sumAttention;
    protected string $sumEmotionsAngry;
    protected string $sumEmotionsDisgusted;
    protected string $sumEmotionsFearful;
    protected string $sumEmotionsHappy;
    protected string $sumEmotionsNeutral;
    protected string $sumEmotionsSad;
    protected string $sumEmotionsSurprised;
    protected string $avgAttention;
    protected string $avgEmotionsAngry;
    protected string $avgEmotionsDisgusted;
    protected string $avgEmotionsFearful;
    protected string $avgEmotionsHappy;
    protected string $avgEmotionsNeutral;
    protected string $avgEmotionsSad;
    protected string $avgEmotionsSurprised;
    protected string $medianAttention;
    protected string $medianEmotionsAngry;
    protected string $medianEmotionsDisgusted;
    protected string $medianEmotionsFearful;
    protected string $medianEmotionsHappy;
    protected string $medianEmotionsNeutral;
    protected string $medianEmotionsSad;
    protected string $medianEmotionsSurprised;
    protected string $aggregatedAt;
    protected bool $shouldBreak;
    protected ?string $recommendedInMinutes = null;
    protected string $reasoning;
    protected string $algorithm;
    protected int $processingTimeMs;
    protected int $meetUsersCount;

    public function model(): AggregatedFrame
    {
        return AggregatedFrame::newModelInstance();
    }

    public function toArray($filters = false): array
    {
        $result = $this->fillInArray($this->model()->getFillable());
        return $filters ? array_filter($result) : $result;
    }

    public function getExternalId(): int
    {
        return $this->externalId;
    }

    public function getModelType(): string
    {
        return $this->modelType;
    }

    public function getModelId(): int
    {
        return $this->modelId;
    }

    public function getTerm(): string
    {
        return $this->term;
    }

    public function getWindowStart(): string
    {
        return $this->windowStart;
    }

    public function getWindowEnd(): string
    {
        return $this->windowEnd;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function getSumAttention(): string
    {
        return $this->sumAttention;
    }

    public function getSumEmotionsAngry(): string
    {
        return $this->sumEmotionsAngry;
    }

    public function getSumEmotionsDisgusted(): string
    {
        return $this->sumEmotionsDisgusted;
    }

    public function getSumEmotionsFearful(): string
    {
        return $this->sumEmotionsFearful;
    }

    public function getSumEmotionsHappy(): string
    {
        return $this->sumEmotionsHappy;
    }

    public function getSumEmotionsNeutral(): string
    {
        return $this->sumEmotionsNeutral;
    }

    public function getSumEmotionsSad(): string
    {
        return $this->sumEmotionsSad;
    }

    public function getSumEmotionsSurprised(): string
    {
        return $this->sumEmotionsSurprised;
    }

    public function getAvgAttention(): string
    {
        return $this->avgAttention;
    }

    public function getAvgEmotionsAngry(): string
    {
        return $this->avgEmotionsAngry;
    }

    public function getAvgEmotionsDisgusted(): string
    {
        return $this->avgEmotionsDisgusted;
    }

    public function getAvgEmotionsFearful(): string
    {
        return $this->avgEmotionsFearful;
    }

    public function getAvgEmotionsHappy(): string
    {
        return $this->avgEmotionsHappy;
    }

    public function getAvgEmotionsNeutral(): string
    {
        return $this->avgEmotionsNeutral;
    }

    public function getAvgEmotionsSad(): string
    {
        return $this->avgEmotionsSad;
    }

    public function getAvgEmotionsSurprised(): string
    {
        return $this->avgEmotionsSurprised;
    }

    public function getMedianAttention(): string
    {
        return $this->medianAttention;
    }

    public function getMedianEmotionsAngry(): string
    {
        return $this->medianEmotionsAngry;
    }

    public function getMedianEmotionsDisgusted(): string
    {
        return $this->medianEmotionsDisgusted;
    }

    public function getMedianEmotionsFearful(): string
    {
        return $this->medianEmotionsFearful;
    }

    public function getMedianEmotionsHappy(): string
    {
        return $this->medianEmotionsHappy;
    }

    public function getMedianEmotionsNeutral(): string
    {
        return $this->medianEmotionsNeutral;
    }

    public function getMedianEmotionsSad(): string
    {
        return $this->medianEmotionsSad;
    }

    public function getMedianEmotionsSurprised(): string
    {
        return $this->medianEmotionsSurprised;
    }

    public function getAggregatedAt(): string
    {
        return $this->aggregatedAt;
    }

    public function getShouldBreak(): bool
    {
        return $this->shouldBreak;
    }

    public function getRecommendedInMinutes(): ?string
    {
        return $this->recommendedInMinutes;
    }

    public function getReasoning(): string
    {
        return $this->reasoning;
    }

    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }

    public function getProcessingTimeMs(): int
    {
        return $this->processingTimeMs;
    }

    public function getMeetUsersCount(): int
    {
        return $this->meetUsersCount;
    }
    public function setMeetUsersCount(int $meetUsersCount): void
    {
        $this->meetUsersCount = $meetUsersCount;
    }
}
