<?php

namespace Tests\APIs;

use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\HeadlessH5P\Dtos\ContentFilterCriteriaDto;
use EscolaLms\HeadlessH5P\Models\H5PContent;
use EscolaLms\HeadlessH5P\Models\H5PLibrary;
use EscolaLms\HeadlessH5P\Repositories\H5PContentRepository;
use EscolaLms\HeadlessH5P\Tests\Traits\H5PTestingTrait;
use EscolaLms\TopicTypes\Models\TopicContent\H5P;
use EscolaLms\TopicTypes\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

class TopicTypeH5PTest extends TestCase
{
    use DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();

        $course = Course::factory()->create();
        $lesson = Lesson::factory()->create([
            'course_id' => $course->id,
        ]);
        $this->topic = Topic::factory()->create([
            'lesson_id' => $lesson->id,
        ]);
        $library = H5PLibrary::factory()->create();
        H5PContent::factory()->create([
            'library_id' => $library->id,
        ]);
        H5PContent::factory()->create([
            'library_id' => $library->id,
        ]);
    }

    public function testH5PCountTopicUses()
    {
        /** @var H5PContentRepository $repository */
        $repository = app(H5PContentRepository::class);
        $h5p = H5PContent::query()->first();
        H5P::factory()->create(['value' => $h5p->id]);
        H5P::factory()->create(['value' => $h5p->id]);

        $list = $repository->list(new ContentFilterCriteriaDto(new Collection([])));

        $this->assertEquals(H5P::query()->where('value', '=', $list[0]->id)->count(), $list[0]->count_h5p);
        $this->assertEquals(H5P::query()->where('value', '=', $list[1]->id)->count(), $list[1]->count_h5p);
    }

    /**
     * @dataProvider h5pProvider
     */
    public function testH5PLength(string $filename, int $length): void
    {
        $filepath = realpath(__DIR__.'/../mocks/'.$filename);
        $storage_path = storage_path($filename);
        copy($filepath, $storage_path);

        /** @var H5PContentRepository $repository */
        $repository = app(H5PContentRepository::class);
        $content = $repository->upload(new UploadedFile($storage_path, $filename, null, null, true));

        $h5p = H5P::factory()->create(['value' => $content->id]);

        $this->assertEquals($length, $h5p->length);
    }

    /**
     * @dataProvider h5pProvider
     */
    public function testH5PLibraryName(string $filename, int $length, string $libraryName): void
    {
        $filepath = realpath(__DIR__.'/../mocks/'.$filename);
        $storage_path = storage_path($filename);
        copy($filepath, $storage_path);

        /** @var H5PContentRepository $repository */
        $repository = app(H5PContentRepository::class);
        $content = $repository->upload(new UploadedFile($storage_path, $filename, null, null, true));

        $h5p = H5P::factory()->create(['value' => $content->id]);

        $this->assertEquals($libraryName, $h5p->libraryName);
    }

    public function h5pProvider(): array
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
