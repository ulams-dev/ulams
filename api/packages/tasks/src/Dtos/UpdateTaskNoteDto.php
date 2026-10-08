<?php

namespace EscolaLms\Tasks\Dtos;

use Illuminate\Http\Request;

class UpdateTaskNoteDto extends CreateTaskNoteDto
{
    private int $id;

    public function __construct(int $id, int $taskId, string $note)
    {
        parent::__construct($taskId, $note);
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
            $request->input('task_id'),
            $request->input('note')
        );
    }
}
