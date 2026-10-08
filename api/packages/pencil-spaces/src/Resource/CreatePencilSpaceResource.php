<?php

namespace EscolaLms\PencilSpaces\Resource;

use EscolaLms\Core\Dtos\Contracts\DtoContract;
use EscolaLms\PencilSpaces\Enums\SpaceVisibilityEnum;
use Illuminate\Support\Collection;

class CreatePencilSpaceResource
{
    private string $title;
    private Collection $hostIds;
    private Collection $participantIds;
    private string $visibility;
    private bool $notifyInvitees;

    public function __construct(
        string     $title,
        Collection $hostIds,
        Collection $participantIds,
        string     $visibility = SpaceVisibilityEnum::PUBLIC,
        bool       $notifyInvitees = false
    )
    {
        $this->title = $title;
        $this->hostIds = $hostIds;
        $this->participantIds = $participantIds;
        $this->visibility = $visibility;
        $this->notifyInvitees = $notifyInvitees;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getHostIds(): Collection
    {
        return $this->hostIds;
    }

    public function getParticipantIds(): Collection
    {
        return $this->participantIds;
    }

    public function getVisibility(): string
    {
        return $this->visibility;
    }

    public function isNotifyInvitees(): bool
    {
        return $this->notifyInvitees;
    }

    public function toArray(): array
    {
        return [
            'title' => $this->getTitle(),
            'visibility' => $this->getVisibility(),
            'notifyInvitees' => $this->isNotifyInvitees(),
        ];
    }
}
