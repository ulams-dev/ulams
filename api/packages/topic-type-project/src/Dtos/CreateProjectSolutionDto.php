<?php

namespace EscolaLms\TopicTypeProject\Dtos;

use EscolaLms\Core\Dtos\Contracts\DtoContract;
use EscolaLms\Core\Dtos\Contracts\InstantiateFromRequest;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class CreateProjectSolutionDto implements DtoContract, InstantiateFromRequest
{
    protected int $topicId;
    protected int $userId;
    protected UploadedFile $file;

    public function __construct(int $topicId, int $userId, UploadedFile $file)
    {
        $this->topicId = $topicId;
        $this->userId = $userId;
        $this->file = $file;
    }

    public function getTopicId(): int
    {
        return $this->topicId;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getFile(): UploadedFile
    {
        return $this->file;
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->getUserId(),
            'topic_id' => $this->getTopicId(),
        ];
    }

    public static function instantiateFromRequest(Request $request): self
    {
        return new static(
            $request->input('topic_id'),
            auth()->id(),
            $request->file('file')
        );
    }
}
