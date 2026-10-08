<?php

namespace EscolaLms\Tasks\Dtos;

use Illuminate\Support\Carbon;
use EscolaLms\Core\Dtos\Contracts\DtoContract;
use EscolaLms\Core\Dtos\Contracts\InstantiateFromRequest;
use Illuminate\Http\Request;

class UpdateTaskDto extends CreateTaskDto implements DtoContract, InstantiateFromRequest
{
    private int $id;

    public function __construct(int $id, string $title, ?string $description, ?string $type, ?Carbon $dueDate, ?string $relatedType, ?int $relatedId)
    {
        parent::__construct($title, $description, $type, $dueDate, $relatedType, $relatedId);

        $this->id = $id;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function toArray(): array
    {
        return parent::toArray() + [
            'id' => $this->getId(),
        ];
    }

    public static function instantiateFromRequest(Request $request): self
    {
        return new static(
            $request->route('id'),
            $request->input('title'),
            $request->input('description'),
            $request->input('type'),
            Carbon::parse($request->input('due_date')),
            $request->input('related_type'),
            $request->input('related_id'),
        );
    }
}

