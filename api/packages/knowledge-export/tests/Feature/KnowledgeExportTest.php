<?php

namespace Ulams\KnowledgeExport\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Opis\JsonSchema\Validator;
use Illuminate\Support\Facades\Storage;
use Ulams\CourseBuilder\Apply\LessonMarkdown;
use Ulams\CourseBuilder\Models\EntityMapEntry;
use Ulams\CourseBuilder\Models\Fragment;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Source;
use Ulams\CourseBuilder\Models\Version;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Topic;
use Ulams\KnowledgeExport\Events\KnowledgeExported;
use Ulams\KnowledgeExport\Models\KnowledgeExport;
use Ulams\KnowledgeExport\Services\KnowledgeExporter;
use Ulams\KnowledgeExport\Tests\TestCase;
use Ulams\TopicTypes\Events\TopicTypeChanged;
use Ulams\TopicTypes\Models\TopicContent\RichText;

class KnowledgeExportTest extends TestCase
{
    private const FRAGMENT_A = 'frg_aaaaaaaaaaaa';

    private const FRAGMENT_B = 'frg_bbbbbbbbbbbb';

    /** A course as the course builder leaves it: two cited blocks, then the "Sources" list, with the blueprint kept. */
    private function builtCourse(): array
    {
        $course = $this->publishedCourse();
        $lesson = $this->lesson($course);
        $blocks = [
            ['id' => 'blk_1', 'kind' => 'text', 'markdown' => "## Texture\n\nMicrofoam looks like wet paint.", 'citations' => [self::FRAGMENT_A]],
            ['id' => 'blk_2', 'kind' => 'callout', 'markdown' => 'Never boil the milk.', 'citations' => [self::FRAGMENT_B, self::FRAGMENT_A]],
        ];
        $markdown = LessonMarkdown::render(['id' => 'lsn_1', 'blocks' => $blocks], [self::FRAGMENT_A => '§1 Texture', self::FRAGMENT_B => '§2 Heat'], 'Barista guide');
        $topic = $this->richTextTopic($lesson, $markdown);

        $session = Session::query()->create(['author_id' => $this->admin()->getKey(), 'course_id' => $course->getKey(), 'status' => 'applied']);
        $source = Source::query()->create([
            'session_id' => $session->id, 'original_name' => 'barista.md', 'mime' => 'text/markdown', 'size' => 10, 'sha256' => str_repeat('0', 64),
            'path' => 'x', 'status' => 'ready', 'metadata' => ['title' => 'Barista guide'],
        ]);
        foreach ([[self::FRAGMENT_A, 'Texture', '1', 'a'], [self::FRAGMENT_B, 'Heat', '2', 'b']] as $i => [$id, $title, $section, $hash]) {
            Fragment::query()->create([
                'id' => $id, 'source_id' => $source->id, 'ordinal' => $i, 'heading_path' => [$title], 'section' => $section, 'level' => 1,
                'text' => $title, 'char_start' => 0, 'char_end' => 5, 'page_start' => 3, 'page_end' => 4, 'token_estimate' => 5, 'content_hash' => str_repeat($hash, 64),
            ]);
        }
        $version = Version::query()->create([
            'session_id' => $session->id, 'number' => 1, 'kind' => 'content', 'origin' => 'ai', 'status' => 'approved',
            'document' => ['modules' => [['id' => 'mod_1', 'lessons' => [['id' => 'lsn_1', 'title' => 'Milk', 'blocks' => $blocks]]]]],
        ]);
        $session->forceFill(['applied_version_id' => $version->id, 'current_version_id' => $version->id])->save();
        EntityMapEntry::query()->create(['session_id' => $session->id, 'element_id' => 'lsn_1', 'entity_type' => 'topic', 'entity_id' => $topic->getKey()]);

        return [$course, $topic];
    }

    private function start(array $body)
    {
        return $this->actingAs($this->admin(), 'api')->postJson('/api/admin/knowledge/exports', $body);
    }

    /** @return list<array<string,mixed>> */
    private function chunks(KnowledgeExport $export, ?int $course = null): array
    {
        $path = KnowledgeExporter::path($export, ($course !== null ? "courses/{$course}/" : '') . 'chunks.jsonl');

        return array_map(fn ($line) => json_decode($line, true), array_values(array_filter(explode("\n", KnowledgeExporter::disk()->get($path)))));
    }

    public function testACourseIsExportedWithCitedChunks(): void
    {
        [$course, $topic] = $this->builtCourse();

        $data = $this->start(['scope' => 'course', 'course_id' => $course->getKey()])->assertStatus(202)->json('data');

        $this->assertSame('ready', $data['status']);
        $this->assertSame(['manifest', 'chunks'], $data['files']);
        $export = KnowledgeExport::query()->findOrFail($data['id']);
        $chunks = $this->chunks($export);
        $this->assertCount(2, $chunks, 'the Sources list is a rendering of the citations, not content');
        $this->assertSame(0, $export->uncited);

        $this->assertSame('Texture', $chunks[0]['heading_path'][0]);
        $this->assertStringContainsString('Microfoam', $chunks[0]['text']);
        $this->assertFalse($chunks[0]['uncited']);
        $this->assertSame([self::FRAGMENT_A], array_column($chunks[0]['citations'], 'fragment_id'));
        $this->assertSame(
            ['label' => '§1 Texture', 'source_name' => 'Barista guide', 'page_start' => 3, 'page_end' => 4],
            array_intersect_key($chunks[0]['citations'][0], array_flip(['label', 'source_name', 'page_start', 'page_end'])),
        );
        $this->assertStringStartsWith('> Never boil', $chunks[1]['text']);
        $this->assertEqualsCanonicalizing([self::FRAGMENT_A, self::FRAGMENT_B], array_column($chunks[1]['citations'], 'fragment_id'));

        $this->assertSame($course->getKey(), $chunks[0]['course_id']);
        $this->assertSame($topic->getKey(), $chunks[0]['topic_id']);
        $this->assertSame('https://academy.test/learn/' . $course->getKey() . '/' . $topic->getKey(), $chunks[0]['url']);
        $this->assertSame(hash('sha256', $chunks[0]['text']), $chunks[0]['content_hash']);
        $this->assertNotSame($chunks[0]['id'], $chunks[1]['id']);

        $manifest = json_decode(KnowledgeExporter::disk()->get(KnowledgeExporter::path($export, 'manifest.json')), true);
        $this->assertSame('ulams-knowledge-pack/1', $manifest['format']);
        $this->assertSame($export->pack_hash, $manifest['pack_hash']);
        $this->assertSame(2, $manifest['chunks']);
        $this->assertSame([['id' => Source::query()->value('id'), 'name' => 'Barista guide']], $manifest['sources']);
    }

    public function testTheFilesMatchThePublishedSchemas(): void
    {
        [$course] = $this->builtCourse();
        $export = KnowledgeExport::query()->findOrFail($this->start(['scope' => 'course', 'course_id' => $course->getKey()])->json('data.id'));
        $schema = fn (string $name) => json_decode((string) file_get_contents(__DIR__ . "/../../resources/schemas/{$name}.json"));
        $validator = new Validator();

        $chunks = $this->chunks($export);
        $this->assertNotEmpty($chunks);
        foreach ($chunks as $chunk) {
            $result = $validator->validate(json_decode(json_encode($chunk)), $schema('knowledge-chunk-1'));
            $this->assertTrue($result->isValid(), json_encode($chunk) . json_encode($result->error()?->message()));
        }
        $manifest = KnowledgeExporter::disk()->get(KnowledgeExporter::path($export, 'manifest.json'));
        $result = $validator->validate(json_decode($manifest), $schema('knowledge-manifest-1'));
        $this->assertTrue($result->isValid(), json_encode($result->error()?->message()));

        $broken = $chunks[0];
        unset($broken['citations']);
        $this->assertFalse($validator->validate(json_decode(json_encode($broken)), $schema('knowledge-chunk-1'))->isValid());
    }

    public function testTextAnAuthorChangedAfterwardsIsExportedAsUncited(): void
    {
        [$course, $topic] = $this->builtCourse();
        $content = $topic->topicable;
        $content->value = str_replace('wet paint', 'wet paint on a wall', $content->value) . "\n\nThe teacher adds: practise daily.";
        $content->save();

        $export = KnowledgeExport::query()->findOrFail($this->start(['scope' => 'course', 'course_id' => $course->getKey()])->json('data.id'));
        $chunks = $this->chunks($export);

        $byText = fn (string $needle) => collect($chunks)->first(fn ($c) => str_contains($c['text'], $needle));
        $this->assertTrue($byText('wet paint on a wall')['uncited'], 'a block that no longer matches verbatim is not cited');
        $this->assertFalse($byText('Never boil')['uncited']);
        $this->assertTrue($byText('practise daily')['uncited']);
        $this->assertSame(2, $export->uncited);
    }

    public function testAHandWrittenCourseIsExportedUncitedWithItsDescription(): void
    {
        $course = $this->publishedCourse(['subtitle' => 'A short course', 'description' => 'Learn to pour hearts and rosettas.']);
        $this->richTextTopic($this->lesson($course), "## Pouring\n\nTilt the cup.");

        $export = KnowledgeExport::query()->findOrFail($this->start(['scope' => 'course', 'course_id' => $course->getKey()])->json('data.id'));
        $chunks = $this->chunks($export);

        $this->assertCount(2, $chunks);
        $this->assertSame('course', $chunks[0]['topic_type']);
        $this->assertNull($chunks[0]['topic_id']);
        $this->assertStringContainsString('rosettas', $chunks[0]['text']);
        $this->assertSame([true, true], array_column($chunks, 'uncited'));
        $this->assertSame([], $chunks[1]['citations']);
        $this->assertSame(2, $export->uncited);
    }

    public function testOnlyLearnerVisibleTextIsExported(): void
    {
        $course = $this->publishedCourse();
        $lesson = $this->lesson($course);
        $this->richTextTopic($lesson, 'Visible text.', ['order' => 1]);
        $this->richTextTopic($lesson, 'Hidden topic.', ['order' => 2, 'active' => false]);
        $this->richTextTopic($this->lesson($course, ['active' => false, 'order' => 2]), 'Hidden lesson.');
        Topic::factory()->create(['lesson_id' => $lesson->getKey(), 'active' => true, 'order' => 3, 'topicable_type' => 'Ulams\TopicTypes\Models\TopicContent\Video', 'topicable_id' => 1]);

        $export = KnowledgeExport::query()->findOrFail($this->start(['scope' => 'course', 'course_id' => $course->getKey()])->json('data.id'));

        $this->assertSame(['Visible text.'], array_column($this->chunks($export), 'text'));
        $manifest = json_decode(KnowledgeExporter::disk()->get(KnowledgeExporter::path($export, 'manifest.json')), true);
        $this->assertSame(['exported' => 1, 'skipped' => 1], $manifest['topics']);
    }

    public function testACourseLearnersCannotSeeIsRejected(): void
    {
        $draft = $this->publishedCourse(['status' => 'draft']);

        $this->start(['scope' => 'course', 'course_id' => $draft->getKey()])->assertStatus(422)->assertJsonValidationErrors('course_id');
        $this->start(['scope' => 'course'])->assertStatus(422);
        $this->start(['scope' => 'course', 'course_id' => 999999])->assertStatus(422);
        $this->start(['scope' => 'tenant', 'course_id' => $draft->getKey()])->assertStatus(422);
        $this->assertSame(0, KnowledgeExport::query()->count());
    }

    public function testATenantExportIsOnePackPerCoursePlusAnIndex(): void
    {
        $first = $this->publishedCourse(['title' => 'Latte art']);
        $this->richTextTopic($this->lesson($first), 'Pour slowly.');
        $second = $this->publishedCourse(['title' => 'Espresso']);
        $this->richTextTopic($this->lesson($second), 'Tamp evenly.');
        $this->publishedCourse(['status' => 'draft', 'title' => 'Not yet']);

        $data = $this->start(['scope' => 'tenant'])->assertStatus(202)->json('data');
        $export = KnowledgeExport::query()->findOrFail($data['id']);

        $this->assertSame(['index', 'manifest', 'chunks'], $data['files']);
        $this->assertSame(2, $export->courses);
        $index = json_decode(KnowledgeExporter::disk()->get(KnowledgeExporter::path($export, 'index.json')), true);
        $this->assertEqualsCanonicalizing([$first->getKey(), $second->getKey()], array_column($index['courses'], 'course_id'));
        $this->assertSame($export->pack_hash, $index['pack_hash']);
        $this->assertSame(['Pour slowly.'], array_column($this->chunks($export, $first->getKey()), 'text'));
        $this->assertSame(['Tamp evenly.'], array_column($this->chunks($export, $second->getKey()), 'text'));
    }

    public function testFilesAreDownloadable(): void
    {
        $course = $this->publishedCourse();
        $this->richTextTopic($this->lesson($course), 'Pour slowly.');
        $admin = $this->admin();
        $id = $this->actingAs($admin, 'api')->postJson('/api/admin/knowledge/exports', ['scope' => 'course', 'course_id' => $course->getKey()])->json('data.id');

        $chunks = $this->actingAs($admin, 'api')->get("/api/admin/knowledge/exports/{$id}/download?file=chunks", ["Accept" => "application/json"])->assertOk();
        $this->assertStringContainsString('application/x-ndjson', $chunks->headers->get('Content-Type'));
        $this->assertStringContainsString('Pour slowly.', $chunks->streamedContent());
        $this->actingAs($admin, 'api')->get("/api/admin/knowledge/exports/{$id}/download?file=manifest", ["Accept" => "application/json"])->assertOk();
        $this->actingAs($admin, 'api')->get("/api/admin/knowledge/exports/{$id}/download?file=index", ["Accept" => "application/json"])->assertStatus(422);

        $tenant = $this->actingAs($admin, 'api')->postJson('/api/admin/knowledge/exports', ['scope' => 'tenant'])->json('data.id');
        $this->actingAs($admin, 'api')->get("/api/admin/knowledge/exports/{$tenant}/download?file=index", ["Accept" => "application/json"])->assertOk();
        $this->actingAs($admin, 'api')->get("/api/admin/knowledge/exports/{$tenant}/download?file=chunks", ["Accept" => "application/json"])->assertStatus(422);
        $this->actingAs($admin, 'api')->get("/api/admin/knowledge/exports/{$tenant}/download?file=chunks&course={$course->getKey()}", ["Accept" => "application/json"])->assertOk();
        $this->actingAs($admin, 'api')->get("/api/admin/knowledge/exports/{$tenant}/download?file=chunks&course=0", ["Accept" => "application/json"])->assertNotFound();
    }

    public function testLatestExportOfACourse(): void
    {
        $course = $this->publishedCourse();
        $this->richTextTopic($this->lesson($course), 'Pour slowly.');
        $admin = $this->admin();

        $this->actingAs($admin, 'api')->getJson("/api/admin/knowledge/courses/{$course->getKey()}/latest")->assertNotFound();

        $id = $this->actingAs($admin, 'api')->postJson('/api/admin/knowledge/exports', ['scope' => 'course', 'course_id' => $course->getKey()])->json('data.id');
        $this->actingAs($admin, 'api')->getJson("/api/admin/knowledge/courses/{$course->getKey()}/latest")->assertOk()->assertJsonPath('data.id', $id);
        $this->actingAs($admin, 'api')->getJson("/api/admin/knowledge/exports/{$id}")->assertOk()->assertJsonPath('data.chunks', 1);
        $this->actingAs($admin, 'api')->getJson('/api/admin/knowledge/exports?course_id=' . $course->getKey())->assertOk()->assertJsonCount(1, 'data');
    }

    public function testAChangedCourseIsExportedAgainAndAnUnchangedOneIsNot(): void
    {
        $course = $this->publishedCourse();
        $topic = $this->richTextTopic($this->lesson($course), 'Pour slowly.');
        $user = $this->admin();
        $first = KnowledgeExport::query()->findOrFail($this->start(['scope' => 'course', 'course_id' => $course->getKey()])->json('data.id'));
        $sent = [];
        Event::listen(KnowledgeExported::class, function (KnowledgeExported $e) use (&$sent) {
            $sent[] = $e->export;
        });

        // an edit that changes nothing
        event(new TopicTypeChanged($user, $topic->topicable));
        $this->assertSame(1, KnowledgeExport::query()->count());
        $this->assertSame([], $sent);

        // a real edit
        $content = $topic->topicable;
        $content->value = 'Pour slowly and steadily.';
        $content->save();
        event(new TopicTypeChanged($user, $content));

        $this->assertSame(2, KnowledgeExport::query()->count());
        $this->assertCount(1, $sent);
        $this->assertSame('auto', $sent[0]->trigger);
        $this->assertNotSame($first->pack_hash, $sent[0]->pack_hash);
        $this->assertSame(['Pour slowly and steadily.'], array_column($this->chunks($sent[0]), 'text'));
    }

    public function testACourseThatWasNeverExportedIsNotExportedAutomatically(): void
    {
        $course = $this->publishedCourse();
        $topic = $this->richTextTopic($this->lesson($course), 'Pour slowly.');

        event(new TopicTypeChanged($this->admin(), $topic->topicable));

        $this->assertSame(0, KnowledgeExport::query()->count());
    }

    public function testAutomaticReexportCanBeSwitchedOffAndSkipsUnpublishedCourses(): void
    {
        $course = $this->publishedCourse();
        $topic = $this->richTextTopic($this->lesson($course), 'Pour slowly.');
        $user = $this->admin();
        $this->start(['scope' => 'course', 'course_id' => $course->getKey()])->assertStatus(202);
        $topic->topicable->update(['value' => 'Changed.']);

        config(['knowledge_export.auto' => false]);
        event(new TopicTypeChanged($user, $topic->topicable));
        $this->assertSame(1, KnowledgeExport::query()->count());

        config(['knowledge_export.auto' => true]);
        Course::query()->whereKey($course->getKey())->update(['status' => 'draft']);
        event(new TopicTypeChanged($user, $topic->topicable));
        $this->assertSame(1, KnowledgeExport::query()->count(), 'nothing is left over for a course learners cannot see');
    }

    public function testOldExportsAreDeletedWithTheirFiles(): void
    {
        config(['knowledge_export.keep' => 2]);
        $course = $this->publishedCourse();
        $this->richTextTopic($this->lesson($course), 'Pour slowly.');
        $ids = [];
        foreach (range(1, 3) as $i) {
            $ids[] = $this->start(['scope' => 'course', 'course_id' => $course->getKey()])->json('data.id');
        }

        $this->assertSame(2, KnowledgeExport::query()->count());
        $this->assertNull(KnowledgeExport::query()->find($ids[0]));
        $this->assertEmpty(Storage::disk(config('filesystems.default'))->allFiles('knowledge-exports/' . $ids[0]));
        $this->assertNotEmpty(Storage::disk(config('filesystems.default'))->allFiles('knowledge-exports/' . $ids[2]));
    }

    public function testOnlyAdminsWithThePermissionCanUseIt(): void
    {
        $course = $this->publishedCourse();
        $this->richTextTopic($this->lesson($course), 'Pour slowly.');
        $id = $this->start(['scope' => 'course', 'course_id' => $course->getKey()])->json('data.id');
        $student = $this->student();

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/admin/knowledge/exports')->assertUnauthorized();
        $this->actingAs($student, 'api')->getJson('/api/admin/knowledge/exports')->assertForbidden();
        $this->actingAs($student, 'api')->postJson('/api/admin/knowledge/exports', ['scope' => 'tenant'])->assertForbidden();
        $this->actingAs($student, 'api')->getJson("/api/admin/knowledge/exports/{$id}")->assertForbidden();
        $this->actingAs($student, 'api')->get("/api/admin/knowledge/exports/{$id}/download?file=chunks", ["Accept" => "application/json"])->assertForbidden();
        $this->actingAs($student, 'api')->getJson("/api/admin/knowledge/courses/{$course->getKey()}/latest")->assertForbidden();
        $this->actingAs($this->admin(), 'api')->getJson('/api/admin/knowledge/exports/01unknown000000000000000000')->assertNotFound();
    }

    public function testAFailedRunIsRecorded(): void
    {
        $course = $this->publishedCourse();
        $export = KnowledgeExport::query()->create(['scope' => 'course', 'course_id' => $course->getKey(), 'status' => 'queued', 'trigger' => 'manual']);
        $course->forceFill(['status' => 'draft'])->save();

        $result = app(KnowledgeExporter::class)->execute($export);

        $this->assertSame('failed', $result->status);
        $this->assertStringContainsString('published', $result->error);
    }
}
