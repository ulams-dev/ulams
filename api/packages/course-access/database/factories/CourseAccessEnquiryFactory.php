<?php

namespace Ulams\CourseAccess\Database\Factories;

use Ulams\CourseAccess\Enum\EnquiryStatusEnum;
use Ulams\CourseAccess\Models\Course;
use Ulams\CourseAccess\Models\CourseAccessEnquiry;
use Ulams\Courses\Tests\Models\User;
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
