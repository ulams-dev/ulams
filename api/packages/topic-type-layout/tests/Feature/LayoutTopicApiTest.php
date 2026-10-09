<?php

namespace Ulams\TopicTypeLayout\Tests\Feature;

use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Database\Seeders\CoursesPermissionSeeder;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\TopicTypeLayout\Models\LayoutTopic;
use Ulams\TopicTypeLayout\Tests\TestCase;

class LayoutTopicApiTest extends TestCase
{
    use CreatesUsers;

    private int $lessonId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoursesPermissionSeeder::class);
        $this->lessonId = Lesson::factory()->for(Course::factory())->create(['active' => true])->getKey();
    }

    private function document(): array
    {
        return [
            ['component' => 'Timeline', 'props' => $this->example('Timeline')['props']],
            ['component' => 'FlipCards', 'props' => $this->example('FlipCards')['props'], 'id' => 'cards'],
            ['component' => 'PracticeActivity', 'props' => $this->example('PracticeActivity')['props']],
        ];
    }

    private function createTopic(array $content)
    {
        return $this->actingAs($this->makeAdmin(), 'api')->postJson('/api/admin/topics', [
            'title' => 'Layout lesson',
            'lesson_id' => $this->lessonId,
            'topicable_type' => LayoutTopic::class,
        ] + $content);
    }

    public function testCreatesALayoutTopicFromAValidDocument(): void
    {
        $this->createTopic(['document' => $this->document(), 'markdown_fallback' => "## Coffee\n\nA timeline, flip cards and practice."])
            ->assertCreated()
            ->assertJsonPath('data.topicable.schema_version', '1')
            ->assertJsonPath('data.topicable.document.0.component', 'Timeline')
            ->assertJsonPath('data.topicable.document.1.id', 'cards')
            ->assertJsonPath('data.topicable.document.2.component', 'PracticeActivity')
            ->assertJsonPath('data.topicable.markdown_fallback', "## Coffee\n\nA timeline, flip cards and practice.");

        $this->assertSame(1, LayoutTopic::query()->count());
        $stored = LayoutTopic::query()->firstOrFail();
        $this->assertSame($this->document(), $stored->document);
    }

    public function testTheDocumentMayBeAJsonStringForMultipartForms(): void
    {
        $this->createTopic(['document' => json_encode($this->document()), 'markdown_fallback' => 'Fallback'])
            ->assertCreated()
            ->assertJsonPath('data.topicable.document.0.component', 'Timeline');
        $this->assertSame($this->document(), LayoutTopic::query()->firstOrFail()->document);

        $this->createTopic(['document' => '{not json', 'markdown_fallback' => 'Fallback'])->assertUnprocessable();
        $this->createTopic(['document' => json_encode([['component' => 'Hero', 'props' => []]]), 'markdown_fallback' => 'Fallback'])->assertUnprocessable();
        $this->assertSame(1, LayoutTopic::query()->count());
    }

    public function testTheTopicIsReadBackThroughTheTopicApi(): void
    {
        $id = $this->createTopic(['document' => $this->document(), 'markdown_fallback' => 'Fallback'])->assertCreated()->json('data.id');

        $this->actingAs($this->makeAdmin(), 'api')->getJson("/api/admin/topics/{$id}")
            ->assertOk()
            ->assertJsonPath('data.topicable_type', LayoutTopic::class)
            ->assertJsonPath('data.topicable.document.0.component', 'Timeline')
            ->assertJsonPath('data.topicable.markdown_fallback', 'Fallback');
    }

    public function testClonedTopicsKeepTheirOwnCopyOfTheDocument(): void
    {
        $id = $this->createTopic(['document' => $this->document(), 'markdown_fallback' => 'Fallback'])->assertCreated()->json('data.id');

        $this->actingAs($this->makeAdmin(), 'api')->postJson("/api/admin/topics/{$id}/clone")->assertSuccessful();

        $this->assertSame(2, LayoutTopic::query()->count());
        $this->assertSame($this->document(), LayoutTopic::query()->latest('id')->firstOrFail()->document);
    }

    public function testEveryApprovedComponentIsAcceptedWithItsExample(): void
    {
        $document = array_map(fn (string $name) => ['component' => $name, 'props' => $this->example($name)['props']], $this->exampleNames());
        $this->assertCount(9, $document);

        $this->createTopic(['document' => $document, 'markdown_fallback' => 'All components'])->assertCreated();
    }

    public function testAnInvalidDocumentIsRejectedAndNothingIsCreated(): void
    {
        $cases = [
            'empty list' => [],
            'a node map instead of a list' => ['component' => 'Timeline', 'props' => $this->example('Timeline')['props']],
            'unknown component' => [['component' => 'Hero', 'props' => ['title' => 'x']]],
            'page-only component' => [['component' => 'Stack', 'props' => [], 'children' => []]],
            'missing component' => [['props' => ['text' => 'x']]],
            'props that break the closed schema' => [['component' => 'Timeline', 'props' => $this->example('Timeline')['invalid']]],
            'an unknown prop' => [['component' => 'Callout', 'props' => ['text' => 'x', 'onclick' => 'alert(1)']]],
            'children on a node' => [['component' => 'Callout', 'props' => ['text' => 'x'], 'children' => []]],
            'a bad anchor id' => [['component' => 'Callout', 'props' => ['text' => 'x'], 'id' => 'a b']],
            'an unsafe link' => [['component' => 'LiaScriptLesson', 'props' => ['src' => 'javascript:alert(1)', 'title' => 'x']]],
            'a practice activity without challenges' => [['component' => 'PracticeActivity', 'props' => $this->example('PracticeActivity')['invalid']]],
        ];
        foreach ($cases as $label => $document) {
            $this->createTopic(['document' => $document, 'markdown_fallback' => 'Fallback'])->assertUnprocessable("{$label} was accepted");
            $this->assertSame(0, LayoutTopic::query()->count(), $label);
        }
    }

    public function testTheErrorNamesTheNodeAndThePath(): void
    {
        $response = $this->createTopic(['document' => [['component' => 'Callout', 'props' => ['text' => 'ok']], ['component' => 'Timeline', 'props' => ['items' => 'nope']]], 'markdown_fallback' => 'x'])
            ->assertUnprocessable();
        $this->assertStringContainsString('/1/props', json_encode($response->json(), JSON_UNESCAPED_SLASHES));
    }

    public function testTheFallbackIsRequired(): void
    {
        $this->createTopic(['document' => $this->document()])->assertUnprocessable();
        $this->createTopic(['document' => $this->document(), 'markdown_fallback' => ''])->assertUnprocessable();
        $this->assertSame(0, LayoutTopic::query()->count());
    }

    public function testOnlySchemaVersionOneIsAccepted(): void
    {
        $this->createTopic(['document' => $this->document(), 'markdown_fallback' => 'x', 'schema_version' => '2'])->assertUnprocessable();
        $this->createTopic(['document' => $this->document(), 'markdown_fallback' => 'x', 'schema_version' => '1'])->assertCreated();
    }

    public function testTheDocumentIsRevalidatedOnUpdate(): void
    {
        $id = $this->createTopic(['document' => $this->document(), 'markdown_fallback' => 'Fallback'])->assertCreated()->json('data.id');
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'api')->patchJson("/api/admin/topics/{$id}", ['topicable_type' => LayoutTopic::class, 'document' => [['component' => 'Hero', 'props' => []]]])
            ->assertUnprocessable();
        $this->assertSame('Timeline', LayoutTopic::query()->firstOrFail()->document[0]['component']);

        $this->actingAs($admin, 'api')->patchJson("/api/admin/topics/{$id}", [
            'topicable_type' => LayoutTopic::class,
            'document' => [['component' => 'Callout', 'props' => ['tone' => 'key', 'text' => 'Changed']]],
            'markdown_fallback' => 'New fallback',
        ])->assertOk()->assertJsonPath('data.topicable.document.0.component', 'Callout');

        $stored = LayoutTopic::query()->firstOrFail();
        $this->assertCount(1, $stored->document);
        $this->assertSame('New fallback', $stored->markdown_fallback);
        $this->assertSame(1, LayoutTopic::query()->count());
    }

    public function testAStudentCannotCreateALayoutTopic(): void
    {
        $this->actingAs($this->makeStudent(), 'api')->postJson('/api/admin/topics', [
            'title' => 'Layout lesson',
            'lesson_id' => $this->lessonId,
            'topicable_type' => LayoutTopic::class,
            'document' => $this->document(),
            'markdown_fallback' => 'x',
        ])->assertForbidden();
        $this->assertSame(0, LayoutTopic::query()->count());
    }

    public function testAnAnonymousVisitorCannotCreateALayoutTopic(): void
    {
        $this->postJson('/api/admin/topics', [
            'title' => 'Layout lesson',
            'lesson_id' => $this->lessonId,
            'topicable_type' => LayoutTopic::class,
            'document' => $this->document(),
            'markdown_fallback' => 'x',
        ])->assertUnauthorized();
    }
}
