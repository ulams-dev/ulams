<?php

namespace EscolaLms\Recommender\Dto;

class MeetRecordingScreenDto extends BaseDto
{
    protected string $modelType;
    protected int $modelId;
    protected string $term;
    protected array $files;

    public function getModelType(): string
    {
        return $this->modelType;
    }

    public function setModelType(string $modelType): void
    {
        $this->modelType = $modelType;
    }

    public function getModelId(): int
    {
        return $this->modelId;
    }

    public function setModelId(int $modelId): void
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

    public function getFiles(): array
    {
        return $this->files;
    }

    public function setFiles(array $files): void
    {
        $this->files = $files;
    }
}
