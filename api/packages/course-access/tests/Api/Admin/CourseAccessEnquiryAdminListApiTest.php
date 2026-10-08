<?php

namespace EscolaLms\CourseAccess\Tests\Api\Admin;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\CourseAccess\Database\Seeders\CourseAccessPermissionSeeder;
use EscolaLms\CourseAccess\Enum\EnquiryStatusEnum;
use EscolaLms\CourseAccess\Models\Course;
use EscolaLms\CourseAccess\Models\CourseAccessEnquiry;
use EscolaLms\CourseAccess\Tests\TestCase;
use Illuminate\Database\Eloquent\Factories\Sequence;

class CourseAccessEnquiryAdminListApiTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CourseAccessPermissionSeeder::class);

        $this->student1 = $this->makeStudent();
        $this->student2 = $this->makeStudent();

        $this->course1 = Course::factory()->create();
        $this->course2 = Course::factory()->create();
        $this->course3 = Course::factory()->create();

        CourseAccessEnquiry::factory()
            ->count(5)
            ->state(new Sequence(
                ['user_id' => $this->student1->getKey(), 'course_id' => $this->course1->getKey()],
                ['user_id' => $this->student1->getKey(), 'course_id' => $this->course2->getKey()],

                ['user_id' => $this->student2->getKey(), 'course_id' => $this->course1->getKey()],
                ['user_id' => $this->student2->getKey(), 'course_id' => $this->course2->getKey(), 'status' => EnquiryStatusEnum::APPROVED],
                ['user_id' => $this->student2->getKey(), 'course_id' => $this->course3->getKey()],
            ))
            ->create();
    }

    public function testCourseAccessEnquiryAdminListUnauthorized(): void
    {
        $this->getJson('api/admin/course-access-enquiries')
            ->assertUnauthorized();
    }

    /**
     * @dataProvider adminFilterDataProvider
     */
    public function testCourseAccessEnquiryAdminList(callable $filter, int $count): void
    {
        $queryParams = $filter($this->student2->getKey(), $this->course2->getKey());

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/course-access-enquiries?' . $queryParams)
            ->assertOk()
            ->assertJsonCount($count, 'data')
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'created_at',
                    'course' => [
                        'id',
                        'name',
                    ],
                    'user' => [
                        'id',
                        'name',
                        'email',
                    ],
                    'status',
                    'data',
                ]]
            ]);
    }


    public function testCourseAccessEnquiryAdminListWithOrder(): void
    {
        CourseAccessEnquiry::query()->delete();

        $course1 = Course::factory()->create();
        $course2 = Course::factory()->create();

        $user1 = $this->makeStudent();
        $user2 = $this->makeStudent();

        $enquiry1 = CourseAccessEnquiry::factory()->create([
            'user_id' => $user1->getKey(),
            'course_id' => $course1->getKey(),
            'status' => EnquiryStatusEnum::APPROVED,
            'created_at' => '2021-01-01 00:00:00'
        ]);

        $enquiry2 = CourseAccessEnquiry::factory()->create([
            'user_id' => $user2->getKey(),
            'course_id' => $course2->getKey(),
            'status' => EnquiryStatusEnum::PENDING,
            'created_at' => '2021-01-02 00:00:00'
        ]);

        $response = $this
            ->actingAs($this->makeAdmin(), 'api')
            ->json('GET', 'api/admin/course-access-enquiries', [
                'order_by' => 'id',
                'order' => 'desc',
            ]);

        $this->assertTrue($response->json('data.0.id') === $enquiry2->getKey());

        $response = $this
            ->actingAs($this->makeAdmin(), 'api')
            ->json('GET', 'api/admin/course-access-enquiries', [
                'order_by' => 'id',
                'order' => 'asc',
            ]);

        $this->assertTrue($response->json('data.0.id') === $enquiry1->getKey());

        $response = $this
            ->actingAs($this->makeAdmin(), 'api')
            ->json('GET', 'api/admin/course-access-enquiries', [
                'order_by' => 'course_id',
                'order' => 'desc',
            ]);

        $this->assertTrue($response->json('data.0.id') === $enquiry2->getKey());

        $response = $this
            ->actingAs($this->makeAdmin(), 'api')
            ->json('GET', 'api/admin/course-access-enquiries', [
                'order_by' => 'course_id',
                'order' => 'asc',
            ]);

        $this->assertTrue($response->json('data.0.id') === $enquiry1->getKey());

        $response = $this
            ->actingAs($this->makeAdmin(), 'api')
            ->json('GET', 'api/admin/course-access-enquiries', [
                'order_by' => 'user_id',
                'order' => 'desc',
            ]);

        $this->assertTrue($response->json('data.0.id') === $enquiry2->getKey());

        $response = $this
            ->actingAs($this->makeAdmin(), 'api')
            ->json('GET', 'api/admin/course-access-enquiries', [
                'order_by' => 'user_id',
                'order' => 'asc',
            ]);

        $this->assertTrue($response->json('data.0.id') === $enquiry1->getKey());

        $response = $this
            ->actingAs($this->makeAdmin(), 'api')
            ->json('GET', 'api/admin/course-access-enquiries', [
                'order_by' => 'created_at',
                'order' => 'desc',
            ]);

        $this->assertTrue($response->json('data.0.id') === $enquiry2->getKey());

        $response = $this
            ->actingAs($this->makeAdmin(), 'api')
            ->json('GET', 'api/admin/course-access-enquiries', [
                'order_by' => 'created_at',
                'order' => 'asc',
            ]);

        $this->assertTrue($response->json('data.0.id') === $enquiry1->getKey());

        $response = $this
            ->actingAs($this->makeAdmin(), 'api')
            ->json('GET', 'api/admin/course-access-enquiries', [
                'order_by' => 'status',
                'order' => 'desc',
            ]);

        $this->assertTrue($response->json('data.0.id') === $enquiry2->getKey());

        $response = $this
            ->actingAs($this->makeAdmin(), 'api')
            ->json('GET', 'api/admin/course-access-enquiries', [
                'order_by' => 'status',
                'order' => 'asc',
            ]);

        $this->assertTrue($response->json('data.0.id') === $enquiry1->getKey());
    }

    public function adminFilterDataProvider(): array
    {
        return [
            [
                'filter' => (function (int $idStudent2, int $idCourse2) {
                    return 'course_id=' . $idCourse2;
                }),
                'count' => 2,
            ],
            [
                'filter' => (function (int $idStudent2) {
                    return 'user_id=' . $idStudent2;
                }),
                'count' => 3,
            ],
            [
                'filter' => (function (int $idStudent2, int $idCourse2) {
                    return 'user_id=' . $idStudent2 . '&course_id=' . $idCourse2;
                }),
                'count' => 1,
            ],
            [
                'filter' => (function () {
                    return 'status=' . EnquiryStatusEnum::APPROVED;
                }),
                'count' => 1,
            ],
        ];
    }
}
