<?php

namespace EscolaLms\Recommender\Dto;

use EscolaLms\Recommender\Models\MeetRecording;

class MeetRecordingDto extends BaseDto
{
    protected string $modelType;
    protected string $modelId;
    protected string $term;
    protected string $action;
    protected ?string $startAt = null;
    protected ?string $endAt = null;
    protected ?string $url = null;
    protected ?int $url_expiration_time_millis = null;

    public function model(): MeetRecording
    {
        // @phpstan-ignore-next-line
        return MeetRecording::newModelInstance();
    }

    public function toArray($filters = false): array
    {
        $result = $this->fillInArray($this->model()->getFillable());
        return $filters ? array_filter($result) : $result;
    }

    public function getModelType(): string
    {
        return $this->modelType;
    }

    public function setModelType(string $modelType): void
    {
        $this->modelType = $modelType;
    }

    public function getModelId(): string
    {
        return $this->modelId;
    }

    public function setModelId(string $modelId): void
    {
        $this->modelId = $modelId;
    }

    public function getTerm(): string
    {
        return $this->term;
    }

    public function setTerm(string $term): void
    {
        $this->term = $term;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): void
    {
        $this->action = $action;
    }

    public function getStartAt(): ?string
    {
        return $this->startAt;
    }

    public function setStartAt(?string $startAt): void
    {
        $this->startAt = $startAt;
    }

    public function getEndAt(): ?string
    {
        return $this->endAt;
    }

    public function setEndAt(?string $endAt): void
    {
        $this->endAt = $endAt;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): void
    {
        $this->url = $url;
    }

    public function getUrlExpirationTimeMillis(): ?int
    {
        return $this->url_expiration_time_millis;
    }

    public function setUrlExpirationTimeMillis(?int $url_expiration_time_millis): void
    {
        $this->url_expiration_time_millis = $url_expiration_time_millis;
    }
}
