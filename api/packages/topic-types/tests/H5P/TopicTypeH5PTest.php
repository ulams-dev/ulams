<?php

namespace Tests\APIs;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\H5P\Database\Factories\H5PContentFactory;
use Ulams\TopicTypes\Http\Resources\TopicType\Admin\H5PResource as AdminH5PResource;
use Ulams\TopicTypes\Http\Resources\TopicType\Client\H5PResource as ClientH5PResource;
use Ulams\TopicTypes\Http\Resources\TopicType\Export\H5PResource as ExportH5PResource;
use Ulams\TopicTypes\Models\TopicContent\H5P;
use Ulams\TopicTypes\Tests\TestCase;

class TopicTypeH5PTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @dataProvider h5pProvider
     */
    public function testH5PLength(string $filename, int $length): void
    {
        $content = H5PContentFactory::fromPackage(realpath(__DIR__ . '/../mocks/' . $filename));

        $h5p = H5P::factory()->create(['value' => $content->id]);

        $this->assertEquals($length, $h5p->length);
    }

    /**
     * @dataProvider h5pProvider
     */
    public function testH5PLibraryName(string $filename, int $length, string $libraryName): void
    {
        $content = H5PContentFactory::fromPackage(realpath(__DIR__ . '/../mocks/' . $filename));

        $h5p = H5P::factory()->create(['value' => $content->id]);

        $this->assertEquals($libraryName, $h5p->libraryName);
    }

    public function testLengthFallsBackToMachineNameForOtherVersions(): void
    {
        $content = H5PContentFactory::create(['library_version' => '1.99']);

        $h5p = H5P::factory()->create(['value' => $content->id]);

        $this->assertEquals(2, $h5p->length);
    }

    public function testLengthIsNullForUnknownLibrary(): void
    {
        $content = H5PContentFactory::create(['main_library' => 'H5P.Unknown', 'library_version' => '1.0']);

        $h5p = H5P::factory()->create(['value' => $content->id]);

        $this->assertNull($h5p->length);
    }

    public function testValueMustBeAnExistingH5PContent(): void
    {
        $content = H5PContentFactory::create();

        $this->assertTrue(validator(['value' => $content->id], H5P::rules())->passes());
        $this->assertTrue(validator(['value' => PHP_INT_MAX], H5P::rules())->fails());
    }

    public function testResourcesReturnContentSummary(): void
    {
        $content = H5PContentFactory::create(['title' => 'Quiz']);
        $course = Course::factory()->create();
        $lesson = Lesson::factory()->create(['course_id' => $course->id]);
        $topic = Topic::factory()->create(['lesson_id' => $lesson->id]);
        $h5p = H5P::factory()->create(['value' => $content->id]);
        $topic->topicable()->associate($h5p)->save();
        $h5p->refresh();

        $summary = [
            'id' => $content->id,
            'title' => 'Quiz',
            'library' => 'H5P.MultiChoice 1.16',
            'main_library' => 'H5P.MultiChoice',
        ];

        $client = (new ClientH5PResource($h5p))->toArray(request());
        $this->assertSame(['id' => $h5p->id, 'value' => $content->id, 'content' => $summary], $client);

        $admin = (new AdminH5PResource($h5p))->toArray(request());
        $this->assertSame($content->id, $admin['value']);
        $this->assertSame($summary, $admin['content']);

        $export = (new ExportH5PResource($h5p))->toArray(request());
        $this->assertSame($content->id, $export['value']);
        $this->assertSame($summary, $export['content']);
        $this->assertSame('topic/' . $topic->id . '/export.h5p', $export['h5p_file']);
        $this->assertArrayNotHasKey('id', $export);

        $this->assertArrayNotHasKey('h5p_content', $h5p->toArray());
    }

    public static function h5pProvider(): array
    {
        return [
            [
                'filename' => 'accordion.h5p',
                'length' => 4,
                'libraryName' => 'H5P.Accordion',
            ],
            [
                'filename' => 'agamotto.h5p',
                'length' => 3,
                'libraryName' => 'H5P.Agamotto',
            ],
            [
                'filename' => 'find-the-hotspot.h5p',
                'length' => 1,
                'libraryName' => 'H5P.ImageHotspotQuestion',
            ],
        ];
    }
}
