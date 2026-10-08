<?php

namespace EscolaLms\Tasks\Dtos;

use Illuminate\Support\Carbon;
use EscolaLms\Core\Dtos\Contracts\DtoContract;
use EscolaLms\Core\Dtos\Contracts\InstantiateFromRequest;
use Illuminate\Http\Request;

class CreateTaskDto implements DtoContract, InstantiateFromRequest
{

    protected string $title;

    protected ?string $description;

    protected ?string $type;

    protected int $userId;

    protected int $createdById;

    protected ?Carbon $dueDate;

    protected ?string $relatedType;

    protected ?int $relatedId;

    public function __construct(string $title, ?string $description, ?string $type, ?Carbon $dueDate, ?string $relatedType, ?int $relatedId)
    {
        $this->title = $title;
        $this->description = $description;
        $this->type = $type;
        $this->userId = auth()->id();
        $this->createdById = auth()->id();
        $this->dueDate = $dueDate;
        $this->relatedType = $relatedType;
        $this->relatedId = $relatedId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getCreatedById(): int
    {
        return $this->createdById;
    }

    public function getDueDate(): ?Carbon
    {
        return $this->dueDate;
    }

    public function getRelatedType(): ?string
    {
        return $this->relatedType;
    }

    public function getRelatedId(): ?int
    {
        return $this->relatedId;
    }

    public function toArray(): array
    {
        return [
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'type' => $this->getType(),
            'user_id' => $this->getUserId(),
            'created_by_id' => $this->getCreatedById(),
            'due_date' => $this->getDueDate(),
            'related_type' => $this->getRelatedType(),
            'related_id' => $this->getRelatedId(),
        ];
    }

    public static function instantiateFromRequest(Request $request): self
    {
        return new static(
            $request->input('title'),
            $request->input('description'),
            $request->input('type'),
            Carbon::parse($request->input('due_date')),
            $request->input('related_type'),
            $request->input('related_id'),
        );
    }
}

