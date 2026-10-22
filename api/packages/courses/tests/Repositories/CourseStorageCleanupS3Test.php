<?php

namespace Ulams\Courses\Tests\Repositories;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Ulams\Core\Tests\Mocks\FakeS3;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Repositories\CourseRepository;
use Ulams\Courses\Tests\TestCase;

/**
 * Deleting a course removes its files from the disk. On S3 `Storage::path()` is only the object key,
 * so the cleanup goes through the disk (ADR 0041), and files of other courses stay.
 */
class CourseStorageCleanupS3Test extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function deleting_a_course_removes_its_objects_and_only_its_own(): void
    {
        $s3 = FakeS3::install();
        $course = Course::factory()->create();
        $other = Course::factory()->create();
        Storage::put("course/{$course->id}/image.png", 'a');
        Storage::put("course/{$course->id}/topic/3/pdf/doc.pdf", 'b');
        Storage::put("course/{$other->id}/image.png", 'c');

        $this->assertTrue(app(CourseRepository::class)->delete($course->id));

        $remaining = array_keys($s3->objects);
        $this->assertSame([], array_values(array_filter($remaining, fn (string $key) => str_starts_with($key, "course/{$course->id}/"))));
        $this->assertContains("course/{$other->id}/image.png", $remaining, 'another course keeps its files');
        $this->assertNull(Course::find($course->id));
    }
}
