<?php

namespace EscolaLms\Consultations\Dto;

class ConsultationUserTermDto extends BaseDto
{
    protected string $term;
    protected ?int $userId = null;

    protected function setTerm(string $term): void
    {
        $this->term = $term;
    }

    public function getTerm(): string
    {
        return $this->term;
    }

    protected function setUserId(int $userId): void
    {
        $this->userId = $userId;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }
}
