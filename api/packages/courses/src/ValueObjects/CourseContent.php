<?php


namespace Ulams\Courses\ValueObjects;


use Ulams\Core\Dtos\Contracts\DtoContract;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Topic;
use Ulams\Courses\Services\Contracts\CourseServiceContract;
use Ulams\Courses\ValueObjects\Contracts\ValueObjectContract;
use Illuminate\Support\Collection;

class CourseContent extends ValueObject implements DtoContract, ValueObjectContract
{
    private CourseServiceContract $courseService;
    private Course $course;
    private array $topics;

    public function __construct(CourseServiceContract $courseService)
    {
        $this->courseService = $courseService;
    }

    public function build(Course $course): self
    {
        $this->course = $course;
        $this->topics = [];

        return $this;
    }

    public function toArray(): array
    {
        return [
            'course' => $this->getCourse()
        ];
    }


    /**
     * @return Course
     */
    public function getCourse(): Course
    {
        return $this->course;
    }

    public function countCertificates(): int
    {
        return 1;
    }
}
