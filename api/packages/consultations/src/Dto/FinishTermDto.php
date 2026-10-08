<?php

namespace EscolaLms\Consultations\Dto;

class FinishTermDto extends BaseDto
{
    protected string $term;
    protected ?string $finishedAt = null;

    protected function setTerm(string $term): void
    {
        $this->term = $term;
    }

    public function getTerm(): string
    {
        return $this->term;
    }

    protected function setFinishedAt(?string $finishedAt): void
    {
        $this->finishedAt = $finishedAt;
    }

    public function getFinishedAt(): ?string
    {
        return $this->finishedAt;
    }
}
