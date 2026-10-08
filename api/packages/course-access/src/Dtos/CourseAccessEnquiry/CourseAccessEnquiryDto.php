<?php

namespace EscolaLms\CourseAccess\Dtos\CourseAccessEnquiry;

use EscolaLms\Core\Dtos\Contracts\DtoContract;

class CourseAccessEnquiryDto implements DtoContract
{
    protected int $courseId;
    protected int $userId;
    protected array $data;
    protected string $status;

    public function __construct(int $courseId, int $userId, array $data, string $status)
    {
        $this->courseId = $courseId;
        $this->userId = $userId;
        $this->data = $data;
        $this->status = $status;
    }

    public function getCourseId(): int
    {
        return $this->courseId;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function toArray(): array
    {
        return [
            'course_id' => $this->getCourseId(),
            'user_id' => $this->getUserId(),
            'data' => $this->getData(),
            'status' => $this->getStatus(),
        ];
    }
}
