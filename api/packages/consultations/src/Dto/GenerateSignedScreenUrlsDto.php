<?php

namespace EscolaLms\Consultations\Dto;

class GenerateSignedScreenUrlsDto extends BaseDto
{
    protected int $consultationId;
    protected int $userTerminId;
    protected string $executedAt;
    protected array $files;
    protected int $userId;

    public function getConsultationId(): int
    {
        return $this->consultationId;
    }

    public function getUserTerminId(): int
    {
        return $this->userTerminId;
    }

    public function getExecutedAt(): string
    {
        return $this->executedAt;
    }

    public function getFiles(): array
    {
        return $this->files;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setFiles(array $files): void
    {
        $this->files = $files;
    }
}
