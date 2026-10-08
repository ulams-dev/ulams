<?php

namespace EscolaLms\Tasks\Dtos;

use Illuminate\Support\Carbon;
use EscolaLms\Core\Dtos\Contracts\DtoContract;
use EscolaLms\Core\Dtos\Contracts\InstantiateFromRequest;
use Illuminate\Http\Request;

class AdminCreateTaskDto extends CreateTaskDto implements DtoContract, InstantiateFromRequest
{

    public function __construct(string $title, ?string $description, ?string $type, ?int $userId, ?Carbon $dueDate, ?string $relatedType, ?int $relatedId)
    {
        parent::__construct($title, $description, $type, $dueDate, $relatedType, $relatedId);

        $this->userId = $userId ?? auth()->id();
    }

    public static function instantiateFromRequest(Request $request): self
    {
        return new static(
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

