<?php

namespace EscolaLms\Tasks\Dtos;

use Illuminate\Support\Carbon;
use EscolaLms\Core\Dtos\Contracts\DtoContract;
use EscolaLms\Core\Dtos\Contracts\InstantiateFromRequest;
use Illuminate\Http\Request;

class AdminUpdateTaskDto extends UpdateTaskDto implements DtoContract, InstantiateFromRequest
{
    public function __construct(int $id, string $title, ?string $description, ?string $type, ?int $userId, ?Carbon $dueDate, ?string $relatedType, ?int $relatedId)
    {
        parent::__construct($id, $title, $description, $type, $dueDate, $relatedType, $relatedId);

        $this->userId = $userId ?? auth()->id();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'type' => $this->getType(),
            'user_id' => $this->getUserId(),
            'due_date' => $this->getDueDate(),
            'related_type' => $this->getRelatedType(),
            'related_id' => $this->getRelatedId(),
        ];
    }

    public static function instantiateFromRequest(Request $request): self
    {
        return new static(
            $request->route('id'),
            $request->input('title'),
            $request->input('description'),
            $request->input('type'),
            $request->input('user_id'),
            Carbon::parse($request->input('due_date')),
            $request->input('related_type'),
            $request->input('related_id'),
        );
    }
}

