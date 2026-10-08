<?php

namespace EscolaLms\CourseAccess\Database\Factories;

use EscolaLms\CourseAccess\Enum\EnquiryStatusEnum;
use EscolaLms\CourseAccess\Models\Course;
use EscolaLms\CourseAccess\Models\CourseAccessEnquiry;
use EscolaLms\Courses\Tests\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseAccessEnquiryFactory extends Factory
{
    protected $model = CourseAccessEnquiry::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'user_id' => User::factory(),
            'status' => EnquiryStatusEnum::PENDING,
        ];
    }

    public function approved(): CourseAccessEnquiryFactory
    {
        return $this->state(function () {
            return [
                'status' => EnquiryStatusEnum::APPROVED,
            ];
        });
    }
}
