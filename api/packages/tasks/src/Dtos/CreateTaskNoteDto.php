<?php

namespace EscolaLms\Tasks\Dtos;

use EscolaLms\Core\Dtos\Contracts\DtoContract;
use EscolaLms\Core\Dtos\Contracts\InstantiateFromRequest;
use Illuminate\Http\Request;

class CreateTaskNoteDto implements DtoContract, InstantiateFromRequest
{
    private int $userId;

    private int $taskId;

    private string $note;

    public function __construct(int $taskId, string $note)
    {
        $this->userId = auth()->id();
        $this->taskId = $taskId;
        $this->note = $note;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getTaskId(): int
    {
        return $this->taskId;
    }

    public function getNote(): string
    {
        return $this->note;
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->getUserId(),
            'task_id' => $this->getTaskId(),
            'note' => $this->getNote(),
        ];
    }

    public static function instantiateFromRequest(Request $request): self
    {
        return new static(
            $request->input('task_id'),
            $request->input('note')
        );
    }
}
